<?php
// Load environment variables
$env_file = __DIR__ . '/../.env';
$env_vars = [];

if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $env_vars[trim($key)] = trim($value);
        }
    }
}

// Database Configuration
define('DB_HOST', $env_vars['DB_HOST'] ?? 'localhost');
define('DB_USER', $env_vars['DB_USER'] ?? 'root');
define('DB_PASSWORD', $env_vars['DB_PASSWORD'] ?? '');
define('DB_NAME', $env_vars['DB_NAME'] ?? 'mini_drive');

// Application Configuration
define('APP_NAME', $env_vars['APP_NAME'] ?? 'MiniDrive');
define('APP_URL', $env_vars['APP_URL'] ?? 'http://localhost/Cloud/mini-drive');
define('MAX_FILE_SIZE', (int)($env_vars['MAX_FILE_SIZE'] ?? 10485760)); // 10MB
define('USER_STORAGE_QUOTA', (int)($env_vars['USER_STORAGE_QUOTA'] ?? 52428800)); // 50MB

// Session Configuration
define('SESSION_TIMEOUT', (int)($env_vars['SESSION_TIMEOUT'] ?? 3600)); // 1 hour
define('MAX_UPLOAD_PER_HOUR', (int)($env_vars['MAX_UPLOAD_PER_HOUR'] ?? 20));

// File paths
$uploads_dir = $env_vars['UPLOADS_DIR'] ?? __DIR__ . '/../uploads';
define('UPLOADS_DIR', realpath($uploads_dir) ?: $uploads_dir);
define('PUBLIC_DIR', __DIR__ . '/../public');

// Ensure uploads directory exists and is writable
if (!is_dir(UPLOADS_DIR)) {
    if (!mkdir(UPLOADS_DIR, 0755, true)) {
        error_log("Failed to create uploads directory: " . UPLOADS_DIR);
    }
}
if (!is_writable(UPLOADS_DIR)) {
    error_log("WARNING: Uploads directory is not writable: " . UPLOADS_DIR);
}

// Enable error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone
date_default_timezone_set('UTC');
?>
