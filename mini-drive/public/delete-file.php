<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$auth->requireLogin();

header('Content-Type: application/json');

$user_id = $auth->getCurrentUser();
$file_id = $_POST['file_id'] ?? 0;
$db = Database::getInstance()->getConnection();

// Check file ownership
$stmt = $db->prepare("SELECT id FROM files WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $file_id, $user_id);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'File not found']);
    exit;
}

// Soft delete
$stmt = $db->prepare("UPDATE files SET deleted_at = NOW() WHERE id = ?");
$stmt->bind_param('i', $file_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'File deleted']);
} else {
    echo json_encode(['success' => false, 'message' => 'Delete failed']);
}
?>
