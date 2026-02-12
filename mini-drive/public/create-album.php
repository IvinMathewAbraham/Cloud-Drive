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

$name = isset($payload['name']) ? trim($payload['name']) : '';
$parentId = isset($payload['parent_id']) ? (int)$payload['parent_id'] : null;

if ($name === '') {
    echo json_encode(['success' => false, 'message' => 'Album name is required']);
    exit;
}

$userId = (int)$auth->getCurrentUser();
$albumService = new AlbumService();

try {
    if ($parentId === null) {
        $root = $albumService->ensureRootAlbum($userId);
        $parentId = (int)$root['id'];
    }

    $album = $albumService->createAlbum($userId, $name, $parentId);
    $albumService->touchAlbum($parentId);

    echo json_encode([
        'success' => true,
        'album' => $album
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
