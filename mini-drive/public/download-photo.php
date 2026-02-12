<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$auth->requireLogin();

$photoId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($photoId <= 0) {
    http_response_code(400);
    exit('Invalid photo identifier');
}

$userId = (int)$auth->getCurrentUser();
$conn = Database::getInstance()->getConnection();

$stmt = $conn->prepare('SELECT * FROM photos WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1');
$stmt->bind_param('ii', $photoId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows === 0) {
    $shareStmt = $conn->prepare(
        'SELECT p.* FROM photos p JOIN photo_sharing ps ON p.id = ps.photo_id WHERE p.id = ? AND ps.shared_with_user_id = ? AND p.deleted_at IS NULL LIMIT 1'
    );
    $shareStmt->bind_param('ii', $photoId, $userId);
    $shareStmt->execute();
    $result = $shareStmt->get_result();

    if (!$result || $result->num_rows === 0) {
        http_response_code(403);
        exit('Access denied');
    }
}

$photo = $result->fetch_assoc();
$filePath = UPLOADS_DIR . '/' . $photo['storage_key'];

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('Photo not found');
}

header('Content-Type: ' . ($photo['mime_type'] ?: 'application/octet-stream'));
header('Content-Disposition: attachment; filename="' . basename($photo['original_filename']) . '"');
header('Content-Length: ' . filesize($filePath));
header('Pragma: public');
header('Cache-Control: private, max-age=0');

readfile($filePath);
?>
