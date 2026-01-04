<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$auth->requireLogin();

header('Content-Type: application/json');

$user_id = $auth->getCurrentUser();
$file_id = $_POST['file_id'] ?? 0;
$email = $_POST['email'] ?? '';
$db = Database::getInstance()->getConnection();

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

// Check file ownership
$stmt = $db->prepare("SELECT id FROM files WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $file_id, $user_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

// Find user by email
$stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

$target_user = $result->fetch_assoc();

// Check existing share
$stmt = $db->prepare("SELECT id FROM file_sharing WHERE file_id = ? AND shared_with_user_id = ?");
$stmt->bind_param('ii', $file_id, $target_user['id']);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Already shared with this user']);
    exit;
}

// Create share
$permission = $_POST['permission'] ?? 'viewer';
$stmt = $db->prepare("INSERT INTO file_sharing (file_id, shared_with_user_id, permission) VALUES (?, ?, ?)");
$stmt->bind_param('iis', $file_id, $target_user['id'], $permission);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'File shared successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Share failed']);
}
?>
