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

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payload']);
    exit;
}

$photoId = isset($payload['photo_id']) ? (int)$payload['photo_id'] : 0;
$targetAlbumId = isset($payload['target_album_id']) ? (int)$payload['target_album_id'] : 0;

if ($photoId <= 0 || $targetAlbumId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Photo and target album are required']);
    exit;
}

$userId = (int)$auth->getCurrentUser();
$albumService = new AlbumService();
$photoService = new PhotoService();

$photo = $photoService->getOwnedPhoto($photoId, $userId);
if (!$photo) {
    echo json_encode(['success' => false, 'message' => 'Photo not found']);
    exit;
}

$targetAlbum = $albumService->getAlbumForUser($targetAlbumId, $userId);
if (!$targetAlbum) {
    echo json_encode(['success' => false, 'message' => 'Target album not found']);
    exit;
}

if ((int)$photo['album_id'] === $targetAlbumId) {
    echo json_encode(['success' => true, 'message' => 'Photo already in target album']);
    exit;
}

$database = Database::getInstance();
$database->beginTransaction();

try {
    if (!$photoService->movePhoto($photoId, $userId, $targetAlbumId)) {
        throw new RuntimeException('Failed to move photo');
    }

    $albumService->touchAlbum((int)$photo['album_id']);
    $albumService->touchAlbum($targetAlbumId);

    $database->commit();
    echo json_encode(['success' => true, 'message' => 'Photo moved']);
} catch (Throwable $e) {
    $database->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
