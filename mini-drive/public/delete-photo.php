<?php
ob_start();

set_error_handler(function ($errno, $errstr) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

set_exception_handler(function ($exception) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $exception->getMessage()]);
    exit;
});

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/photo_drive.php';

header('Content-Type: application/json');
ob_end_clean();

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'not_authenticated']);
    exit;
}

$photoId = isset($_POST['photo_id']) ? (int)$_POST['photo_id'] : 0;
if ($photoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid photo identifier']);
    exit;
}

$userId = (int)$auth->getCurrentUser();
$photoService = new PhotoService();
$albumService = new AlbumService();
$database = Database::getInstance();
$conn = $database->getConnection();

$photo = $photoService->getOwnedPhoto($photoId, $userId);
if (!$photo) {
    echo json_encode(['success' => false, 'message' => 'Photo not found']);
    exit;
}

$database->beginTransaction();

try {
    $deleteMeta = $photoService->softDeletePhoto($photoId, $userId);
    if (!$deleteMeta) {
        throw new RuntimeException('Unable to delete photo');
    }

    $photoService->updateUserStorage($userId, -$deleteMeta['file_size']);
    $albumService->touchAlbum((int)$photo['album_id']);

    $database->commit();
} catch (Throwable $e) {
    $database->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

// Perform filesystem cleanup outside transaction
$storagePath = UPLOADS_DIR . '/' . $deleteMeta['storage_key'];
if (file_exists($storagePath)) {
    unlink($storagePath);
}

if (!empty($deleteMeta['thumbnail_path'])) {
    $thumbPath = UPLOADS_DIR . '/' . $deleteMeta['thumbnail_path'];
    if (file_exists($thumbPath)) {
        unlink($thumbPath);
    }
}

echo json_encode(['success' => true, 'message' => 'Photo deleted']);
?>
