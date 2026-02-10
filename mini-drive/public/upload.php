<?php
// Start output buffering to catch any stray output
ob_start();

// Set error handler to convert errors to JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $errstr]);
    exit;
});

// Set exception handler
set_exception_handler(function($exception) {
    ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $exception->getMessage()]);
    exit;
});

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');
ob_end_clean();

$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'not_authenticated']);
    exit;
}

$user_id = $auth->getCurrentUser();
$db = Database::getInstance()->getConnection();

// Check rate limiting
$hour_key = date('YmdH');
$stmt = $db->prepare("SELECT upload_count FROM upload_rate_limit WHERE user_id = ? AND hour_key = ?");
$stmt->bind_param('is', $user_id, $hour_key);
$stmt->execute();
$rate_limit = $stmt->get_result()->fetch_assoc();

$upload_count = $rate_limit ? $rate_limit['upload_count'] : 0;

if ($upload_count >= MAX_UPLOAD_PER_HOUR) {
    echo json_encode(['success' => false, 'message' => 'Upload limit reached. Try again later.']);
    exit;
}

// Validate file
if (!isset($_FILES['file'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error']);
    exit;
}

if ($file['size'] > MAX_FILE_SIZE) {
    echo json_encode(['success' => false, 'message' => 'File too large']);
    exit;
}

// Check user storage
$user_info = $auth->getUserInfo($user_id);
if ($user_info['storage_used'] + $file['size'] > USER_STORAGE_QUOTA) {
    echo json_encode(['success' => false, 'message' => 'Storage quota exceeded']);
    exit;
}

// Verify uploads directory exists and is writable
if (!is_dir(UPLOADS_DIR)) {
    error_log("Upload failed: Uploads directory doesn't exist: " . UPLOADS_DIR);
    echo json_encode(['success' => false, 'message' => 'Upload directory not configured']);
    exit;
}

if (!is_writable(UPLOADS_DIR)) {
    error_log("Upload failed: Uploads directory not writable: " . UPLOADS_DIR . " (Permissions: " . substr(sprintf('%o', fileperms(UPLOADS_DIR)), -4) . ")");
    echo json_encode(['success' => false, 'message' => 'Upload directory not writable. Please contact administrator.']);
    exit;
}
// Generate unique filename
$original_filename = basename($file['name']);
$file_ext = pathinfo($original_filename, PATHINFO_EXTENSION);
$filename = uniqid() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;

// Create user upload directory
$user_upload_dir = UPLOADS_DIR . '/' . $user_id;
if (!is_dir($user_upload_dir)) {
        if (!mkdir($user_upload_dir, 0755, true)) {
        error_log("Failed to create user directory: $user_upload_dir");
        echo json_encode(['success' => false, 'message' => 'Failed to create upload directory']);
        exit;
    }
}

$file_path = $user_upload_dir . '/' . $filename;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
     $error_msg = error_get_last();
    error_log("Failed to move uploaded file. Tmp: {$file['tmp_name']}, Dest: $file_path, Error: " . ($error_msg['message'] ?? 'unknown'));
    echo json_encode(['success' => false, 'message' => 'Failed to save file. Check server permissions.']);
    exit;
}

// Encrypt file (optional - simple encryption)
$is_encrypted = false;
$encryption_key = null;
if ($file['size'] > 1024 * 1024) { // Encrypt files > 1MB
    $encryption_key = bin2hex(random_bytes(32));
    encryptFile($file_path, $encryption_key);
    $is_encrypted = true;
}

// Store in database
$stmt = $db->prepare("INSERT INTO files (user_id, filename, original_filename, file_path, file_size, file_type, is_encrypted, encryption_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$file_type = mime_content_type($file_path) ?: 'application/octet-stream';
$stmt->bind_param(
    'issssiss',
    $user_id,
    $filename,
    $original_filename,
    $file_path,
    $file['size'],
    $file_type,
    $is_encrypted,
    $encryption_key
);

if (!$stmt->execute()) {
    unlink($file_path);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

// Update user storage
$new_storage = $user_info['storage_used'] + $file['size'];
$stmt = $db->prepare("UPDATE users SET storage_used = ? WHERE id = ?");
$stmt->bind_param('ii', $new_storage, $user_id);
$stmt->execute();

// Update rate limit
if ($rate_limit) {
    $new_count = $upload_count + 1;
    $stmt = $db->prepare("UPDATE upload_rate_limit SET upload_count = ? WHERE user_id = ? AND hour_key = ?");
    $stmt->bind_param('iis', $new_count, $user_id, $hour_key);
} else {
    $stmt = $db->prepare("INSERT INTO upload_rate_limit (user_id, upload_count, hour_key) VALUES (?, 1, ?)");
    $stmt->bind_param('is', $user_id, $hour_key);
}
$stmt->execute();


echo json_encode(['success' => true, 'message' => 'File uploaded successfully']);

function encryptFile($file_path, $key) {
    $data = file_get_contents($file_path);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', hex2bin($key), OPENSSL_RAW_DATA, $iv);
    file_put_contents($file_path, $iv . $encrypted);
}
?>
