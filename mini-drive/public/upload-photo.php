<?php
ob_start();

/**
 * Quietly drop the active output buffer when present.
 */
function clear_output_buffer(): void
{
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
}

set_error_handler(function ($errno, $errstr) {
    clear_output_buffer();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

set_exception_handler(function ($exception) {
    clear_output_buffer();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $exception->getMessage()]);
    exit;
});

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/media.php';
require_once __DIR__ . '/../includes/photo_drive.php';

header('Content-Type: application/json');
clear_output_buffer();

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'not_authenticated']);
    exit;
}

if (!isset($_FILES['photo'])) {
    echo json_encode(['success' => false, 'message' => 'No photo uploaded']);
    exit;
}

$userId = (int)$auth->getCurrentUser();
$albumService = new AlbumService();
$photoService = new PhotoService();
$database = Database::getInstance();
$conn = $database->getConnection();

$albumId = isset($_POST['album_id']) ? (int)$_POST['album_id'] : null;
$targetAlbum = null;

if ($albumId) {
    $targetAlbum = $albumService->getAlbumForUser($albumId, $userId);
    if (!$targetAlbum) {
        echo json_encode(['success' => false, 'message' => 'Album not found']);
        exit;
    }
} else {
    $targetAlbum = $albumService->ensureRootAlbum($userId);
    $albumId = (int)$targetAlbum['id'];
}

$file = $_FILES['photo'];
$validation = Media::validateImageUpload($file);
if (!$validation['success']) {
    echo json_encode($validation);
    exit;
}

$hourKey = date('YmdH');
$rateStmt = $conn->prepare('SELECT ingest_count FROM ingest_rate_limit WHERE user_id = ? AND hour_key = ?');
$rateStmt->bind_param('is', $userId, $hourKey);
$rateStmt->execute();
$rateResult = $rateStmt->get_result();
$rateRow = $rateResult ? $rateResult->fetch_assoc() : null;
$ingestCount = $rateRow ? (int)$rateRow['ingest_count'] : 0;

if ($ingestCount >= MAX_UPLOAD_PER_HOUR) {
    echo json_encode(['success' => false, 'message' => 'Upload limit reached. Try again later.']);
    exit;
}

$userInfo = $auth->getUserInfo($userId);
if ($userInfo['storage_used'] + $file['size'] > USER_PHOTO_QUOTA) {
    echo json_encode(['success' => false, 'message' => 'User storage quota exceeded']);
    exit;
}

$albumUsage = $albumService->getAlbumUsageBytes($albumId);
if ($albumUsage + $file['size'] > ALBUM_STORAGE_QUOTA) {
    echo json_encode(['success' => false, 'message' => 'Album storage quota exceeded']);
    exit;
}

$originalName = basename($file['name']);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if ($extension === '') {
    $extension = substr(strrchr($validation['mime'], '/'), 1) ?: 'jpg';
}

$storageKey = Media::generateStorageKey($userId, $extension);
$storagePath = UPLOADS_DIR . '/' . $storageKey;

if (!Media::moveUpload($file['tmp_name'], $storagePath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to store photo']);
    exit;
}

$checksum = hash_file('sha256', $storagePath);
if ($photoService->checksumExists($userId, $checksum)) {
    unlink($storagePath);
    echo json_encode(['success' => false, 'message' => 'Duplicate photo detected']);
    exit;
}

$metadata = Media::extractImageMetadata($storagePath);
$thumbnailExtension = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $extension : 'jpg';
$thumbnailKey = Media::generateThumbnailKey($userId, $thumbnailExtension);
$thumbnailPath = UPLOADS_DIR . '/' . $thumbnailKey;

if (!Media::createThumbnail($storagePath, $thumbnailPath)) {
    $thumbnailKey = null;
    $thumbnailPath = null;
}

$database->beginTransaction();

try {
    $photoId = $photoService->insertPhoto([
        'user_id' => $userId,
        'album_id' => $albumId,
        'storage_key' => $storageKey,
        'original_filename' => $originalName,
        'mime_type' => $validation['mime'],
        'file_size' => $file['size'],
        'width' => $metadata['width'],
        'height' => $metadata['height'],
        'exif_taken_at' => $metadata['exif_taken_at'],
        'exif_camera_make' => $metadata['exif_camera_make'],
        'exif_camera_model' => $metadata['exif_camera_model'],
        'exif_focal_length' => $metadata['exif_focal_length'],
        'exif_aperture' => $metadata['exif_aperture'],
        'exif_iso' => $metadata['exif_iso'],
        'checksum' => $checksum,
        'thumbnail_path' => $thumbnailKey,
    ]);

    $photoService->updateUserStorage($userId, $file['size']);
    $albumService->touchAlbum($albumId);

    if ($rateRow) {
        $newCount = $ingestCount + 1;
        $updateRate = $conn->prepare('UPDATE ingest_rate_limit SET ingest_count = ? WHERE user_id = ? AND hour_key = ?');
        $updateRate->bind_param('iis', $newCount, $userId, $hourKey);
        $updateRate->execute();
    } else {
        $insertRate = $conn->prepare('INSERT INTO ingest_rate_limit (user_id, ingest_count, hour_key) VALUES (?, 1, ?)');
        $insertRate->bind_param('is', $userId, $hourKey);
        $insertRate->execute();
    }

    $database->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Photo uploaded successfully',
        'photo' => [
            'id' => $photoId,
            'album_id' => $albumId,
            'original_filename' => $originalName,
            'thumbnail' => $thumbnailKey,
            'mime_type' => $validation['mime'],
            'width' => $metadata['width'],
            'height' => $metadata['height'],
            'file_size' => $file['size']
        ]
    ]);
} catch (DuplicatePhotoException $e) {
    $database->rollback();
    if (isset($storagePath) && file_exists($storagePath)) {
        unlink($storagePath);
    }
    if (isset($thumbnailPath) && $thumbnailPath && file_exists($thumbnailPath)) {
        unlink($thumbnailPath);
    }
    echo json_encode(['success' => false, 'message' => 'Duplicate photo detected']);
    exit;
} catch (Throwable $e) {
    $database->rollback();
    if (isset($storagePath) && file_exists($storagePath)) {
        unlink($storagePath);
    }
    if (isset($thumbnailPath) && $thumbnailPath && file_exists($thumbnailPath)) {
        unlink($thumbnailPath);
    }
    throw $e;
}
?>
