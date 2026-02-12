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

$userId = (int)$auth->getCurrentUser();
$photoId = isset($_POST['photo_id']) ? (int)$_POST['photo_id'] : 0;
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$permission = isset($_POST['permission']) ? $_POST['permission'] : 'viewer';

if ($photoId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid photo identifier']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

$photoService = new PhotoService();
$photo = $photoService->getOwnedPhoto($photoId, $userId);
if (!$photo) {
    echo json_encode(['success' => false, 'message' => 'Photo not found']);
    exit;
}

$conn = Database::getInstance()->getConnection();

$stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$target = $result->fetch_assoc();
$targetId = (int)$target['id'];

if ($targetId === $userId) {
    echo json_encode(['success' => false, 'message' => 'Cannot share with yourself']);
    exit;
}

$validPermissions = ['viewer', 'editor'];
if (!in_array($permission, $validPermissions, true)) {
    $permission = 'viewer';
}

$existsStmt = $conn->prepare('SELECT id FROM photo_sharing WHERE photo_id = ? AND shared_with_user_id = ? LIMIT 1');
$existsStmt->bind_param('ii', $photoId, $targetId);
$existsStmt->execute();
$exists = $existsStmt->get_result();
if ($exists && $exists->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Already shared with this user']);
    exit;
}

$insertStmt = $conn->prepare('INSERT INTO photo_sharing (photo_id, shared_with_user_id, permission) VALUES (?, ?, ?)');
$insertStmt->bind_param('iis', $photoId, $targetId, $permission);

if ($insertStmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Photo shared successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Share failed']);
}
?>
