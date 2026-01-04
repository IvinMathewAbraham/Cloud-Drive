<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$auth->requireLogin();

$user_id = $auth->getCurrentUser();
$file_id = $_GET['id'] ?? 0;
$db = Database::getInstance()->getConnection();

// Check file ownership or sharing
$stmt = $db->prepare("SELECT id, filename, file_path, original_filename, is_encrypted, encryption_key, file_type FROM files WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $file_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Check if shared with user
    $stmt = $db->prepare("
        SELECT f.id, f.filename, f.file_path, f.original_filename, f.is_encrypted, f.encryption_key, f.file_type
        FROM files f
        JOIN file_sharing fs ON f.id = fs.file_id
        WHERE f.id = ? AND fs.shared_with_user_id = ?
    ");
    $stmt->bind_param('ii', $file_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(403);
        die('Access denied');
    }
}

$file = $result->fetch_assoc();
$file_path = $file['file_path'];
$file_type = $file['file_type'] ?? 'application/octet-stream';
$ext = strtolower(pathinfo($file['original_filename'], PATHINFO_EXTENSION));

if (!file_exists($file_path)) {
    http_response_code(404);
    die('File not found');
}

// Read file content
$content = file_get_contents($file_path);

// Decrypt if needed
if ($file['is_encrypted']) {
    $content = decryptFile($content, $file['encryption_key']);
}

// Determine display type
$imageExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'];
$textExts = ['txt', 'md', 'html', 'htm', 'css', 'js', 'php', 'json', 'xml', 'csv', 'sql', 'log'];
$codeExts = ['py', 'java', 'cpp', 'c', 'js', 'ts', 'php', 'rb', 'go', 'rs', 'sh', 'bash'];

if (in_array($ext, $imageExts)) {
    header('Content-Type: image/' . ($ext === 'svg' ? 'svg+xml' : $ext));
    header('Content-Disposition: inline; filename="' . basename($file['original_filename']) . '"');
} elseif ($ext === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . basename($file['original_filename']) . '"');
} elseif (in_array($ext, $textExts)) {
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: inline; filename="' . basename($file['original_filename']) . '"');
} else {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: inline; filename="' . basename($file['original_filename']) . '"');
}

header('Content-Length: ' . strlen($content));
header('Pragma: no-cache');
header('Expires: 0');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo $content;

function decryptFile($encrypted_data, $key) {
    $iv = substr($encrypted_data, 0, 16);
    $encrypted = substr($encrypted_data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', hex2bin($key), OPENSSL_RAW_DATA, $iv);
}
?>
