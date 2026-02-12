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

/**
 * Look up configuration values in process env first, then .env fallback.
 */
function get_env_value(string $key, $default, array $env_vars)
{
    if (array_key_exists($key, $_ENV)) {
        return $_ENV[$key];
    }

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (array_key_exists($key, $env_vars)) {
        return $env_vars[$key];
    }

    return $default;
}

// Database Configuration
define('DB_HOST', get_env_value('DB_HOST', 'db', $env_vars));
define('DB_USER', get_env_value('DB_USER', 'photo_user', $env_vars));
define('DB_PASSWORD', get_env_value('DB_PASSWORD', 'photo_pass', $env_vars));
define('DB_NAME', get_env_value('DB_NAME', 'photo_drive', $env_vars));

// Application Configuration
define('APP_NAME', get_env_value('APP_NAME', 'PhotoDrive', $env_vars));
define('APP_URL', get_env_value('APP_URL', 'http://localhost:5003', $env_vars));

define('PHOTO_MAX_SIZE', (int)get_env_value('PHOTO_MAX_SIZE', 10485760, $env_vars));
define('USER_PHOTO_QUOTA', (int)get_env_value('USER_PHOTO_QUOTA', 52428800, $env_vars));
define('ALBUM_STORAGE_QUOTA', (int)get_env_value('ALBUM_STORAGE_QUOTA', 26214400, $env_vars));
define('SESSION_TIMEOUT', (int)get_env_value('SESSION_TIMEOUT', 3600, $env_vars));
define('MAX_UPLOAD_PER_HOUR', (int)get_env_value('MAX_UPLOAD_PER_HOUR', 20, $env_vars));

// File paths
$uploads_dir = get_env_value('UPLOADS_DIR', (__DIR__ . '/../uploads'), $env_vars);
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
$is_production = get_env_value('APP_ENV', 'development', $env_vars) === 'production';
if ($is_production) {
    error_reporting(0);
    ini_set('display_errors', 0);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Timezone
date_default_timezone_set(get_env_value('TZ', 'UTC', $env_vars));
?>
