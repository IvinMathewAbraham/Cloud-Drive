<?php
// Load environment variables
$env_file = __DIR__ . '/../.env';
$env_vars = [];

if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {

        // Ignore full-line comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Split only on first "="
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Remove optional surrounding quotes
            $value = trim($value, "\"'");

            $env_vars[$key] = $value;
        }
    }
}

// Database Configuration
define('DB_HOST', $env_vars['DB_HOST'] ?? 'db');
define('DB_USER', $env_vars['DB_USER'] ?? 'mini_user');
define('DB_PASSWORD', $env_vars['DB_PASSWORD'] ?? 'mini_pass');  // FIXED
define('DB_NAME', $env_vars['DB_NAME'] ?? 'mini_drive');

// Application Configuration
define('APP_NAME', $env_vars['APP_NAME'] ?? 'MiniDrive');
define('APP_URL', $env_vars['APP_URL'] ?? 'http://localhost:8080');

define('MAX_FILE_SIZE', (int)($env_vars['MAX_FILE_SIZE'] ?? 10485760));       // 10 MB
define('USER_STORAGE_QUOTA', (int)($env_vars['USER_STORAGE_QUOTA'] ?? 52428800)); // 50 MB
define('SESSION_TIMEOUT', (int)($env_vars['SESSION_TIMEOUT'] ?? 3600));
define('MAX_UPLOAD_PER_HOUR', (int)($env_vars['MAX_UPLOAD_PER_HOUR'] ?? 20));

// File paths
$uploads_dir = $env_vars['UPLOADS_DIR'] ?? (__DIR__ . '/../uploads');
define('UPLOADS_DIR', realpath($uploads_dir) ?: $uploads_dir);
define('PUBLIC_DIR', __DIR__ . '/../public');

// Ensure uploads directory exists
if (!is_dir(UPLOADS_DIR)) {
    mkdir(UPLOADS_DIR, 0755, true);
}

// Ensure writability
if (!is_writable(UPLOADS_DIR)) {
    error_log("WARNING: Uploads directory is not writable: " . UPLOADS_DIR);
}

// Error reporting (disable in production, enable for development)
$is_production = ($env_vars['APP_ENV'] ?? 'development') === 'production';
if ($is_production) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Timezone
date_default_timezone_set($env_vars['TZ'] ?? 'UTC');
?>
