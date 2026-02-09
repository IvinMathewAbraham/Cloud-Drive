# MiniDrive - Issues & Production Readiness Analysis

**Analysis Date:** February 9, 2026  
**Status:** ⚠️ NOT Production Ready - Critical Issues Identified

---

## **CRITICAL ISSUES** 🚨

### **1. Docker Configuration Problems**

**Issues:**
- ❌ **Dockerfile** is misspelled as `DockerFile` (case-sensitive on Linux)
- ❌ Container switches to `www-data` user BEFORE running entrypoint (permission issues)
- ❌ No `.env` file exists, but docker-compose expects environment variables
- ❌ Missing app health checks
- ❌ Uploads directory might have permission conflicts

**Solutions:**
```bash
# Rename: docker/DockerFile → docker/Dockerfile
mv docker/DockerFile docker/Dockerfile

# Create .env file
cat > .env << EOF
DB_HOST=db
DB_USER=mini_user
DB_PASSWORD=mini_pass
DB_NAME=mini_drive
APP_URL=http://localhost:8080
MAX_FILE_SIZE=10485760
USER_STORAGE_QUOTA=52428800
SESSION_TIMEOUT=3600
MAX_UPLOAD_PER_HOUR=20
EOF
```

**Fix Dockerfile order:**
```dockerfile
# USER directive should be LAST (after ENTRYPOINT setup)
# Current (WRONG):
USER www-data
ENTRYPOINT ["/entrypoint.sh"]

# Should be (CORRECT):
ENTRYPOINT ["/entrypoint.sh"]
# Don't set USER here - let entrypoint handle permissions
```

**Add health checks to docker-compose.yml:**
```yaml
services:
  app:
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health.php"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
    restart: always
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: '0.5'
```

---

### **2. SEVERE Security Vulnerabilities** 🔒

**Issues:**
- ❌ **No CSRF protection** - all POST endpoints vulnerable to cross-site attacks
- ❌ **Weak passwords** - 6 chars min (should be 8+)
- ❌ **Session fixation** - no session regeneration after login
- ❌ **Rate limiting in $_SESSION** - easily bypassed by clearing cookies
- ❌ **Encryption keys in database** - single point of failure
- ❌ **No security headers** (X-Frame-Options, CSP only on index.php)
- ❌ **No account lockout** after failed logins
- ❌ **Missing input sanitization** in multiple places
- ❌ **Directory traversal risk** in file paths
- ❌ **Error display enabled** - exposes sensitive information

**Solutions:**

#### 2.1 CSRF Protection
```php
// includes/csrf.php
class CSRF {
    public static function generateToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public static function validateToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Add to all forms:
<input type="hidden" name="csrf_token" value="<?php echo CSRF::generateToken(); ?>">

// Validate in handlers:
if (!CSRF::validateToken($_POST['csrf_token'] ?? '')) {
    die('CSRF validation failed');
}
```

#### 2.2 Session Fixation Fix (auth.php)
```php
// After successful login (line ~95):
session_regenerate_id(true); // Add this line
$_SESSION['user_id'] = $user['id'];
```

#### 2.3 Security Headers Middleware
```php
// includes/security-headers.php
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com;");

// Include this at the top of every public page
```

#### 2.4 Persistent Rate Limiting (Redis/Database)
```php
// Replace session-based rate limiting in auth.php with database:
private function checkRateLimit($identifier, $limit = 5, $window = 3600) {
    $stmt = $this->db->prepare("
        SELECT COUNT(*) as attempts 
        FROM login_attempts 
        WHERE identifier = ? 
        AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
    ");
    $stmt->bind_param('si', $identifier, $window);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['attempts'] >= $limit) {
        return false;
    }
    
    // Log attempt
    $stmt = $this->db->prepare("INSERT INTO login_attempts (identifier, attempted_at) VALUES (?, NOW())");
    $stmt->bind_param('s', $identifier);
    $stmt->execute();
    
    return true;
}

// Add table:
CREATE TABLE login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier_time (identifier, attempted_at)
);
```

#### 2.5 Fix Production Error Display (config.php)
```php
// Line 55-56: Change to
$is_production = ($_ENV['APP_ENV'] ?? 'production') === 'production';
error_reporting($is_production ? 0 : E_ALL);
ini_set('display_errors', $is_production ? '0' : '1');
```

---

### **3. Database Design Flaws** 📊

**Issues:**
- ❌ **Storage recalculated on EVERY page load** (index.php lines 18-27) - expensive operation
- ❌ **Soft delete doesn't reclaim quota** - storage leak
- ❌ **Absolute file paths** - breaks Docker portability
- ❌ **Missing indexes** on user_id, deleted_at (slow queries at scale)
- ❌ **Rate limit table never cleaned** - grows forever
- ❌ **No database migrations system**

**Solutions:**

#### 3.1 Database Triggers for Storage Calculation
```sql
-- Auto-update storage_used on file insert
DELIMITER $$
CREATE TRIGGER update_storage_on_insert
AFTER INSERT ON files
FOR EACH ROW
BEGIN
    IF NEW.deleted_at IS NULL THEN
        UPDATE users 
        SET storage_used = storage_used + NEW.file_size 
        WHERE id = NEW.user_id;
    END IF;
END$$

-- Auto-update storage_used on file delete
CREATE TRIGGER update_storage_on_delete
AFTER UPDATE ON files
FOR EACH ROW
BEGIN
    IF OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL THEN
        UPDATE users 
        SET storage_used = storage_used - NEW.file_size 
        WHERE id = NEW.user_id;
    END IF;
END$$
DELIMITER ;
```

#### 3.2 Add Critical Indexes
```sql
-- Performance indexes
CREATE INDEX idx_user_files ON files(user_id, deleted_at);
CREATE INDEX idx_file_sharing ON file_sharing(shared_with_user_id, file_id);
CREATE INDEX idx_rate_limit_hour ON upload_rate_limit(user_id, hour_key);
CREATE INDEX idx_created_at ON files(created_at);
```

#### 3.3 Store Relative Paths
```php
// In upload.php, change line 73:
// From: $file_path = $user_upload_dir . '/' . $filename;
// To: $relative_path = 'uploads/' . $user_id . '/' . $filename;
//     $file_path = UPLOADS_DIR . '/' . $user_id . '/' . $filename;

// Store $relative_path in database instead of absolute path
```

#### 3.4 Cleanup Old Records (Cron Job)
```php
// scripts/cleanup.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = Database::getInstance()->getConnection();

// Delete rate limit records older than 24 hours
$db->query("DELETE FROM upload_rate_limit WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

// Delete login attempts older than 24 hours
$db->query("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");

echo "Cleanup completed\n";
```

```bash
# Add to crontab
0 2 * * * php /var/www/html/scripts/cleanup.php
```

---

### **4. File Management Disasters** 📁

**Issues:**
- ❌ **Soft deleted files NEVER physically deleted** - endless storage leak
- ❌ **Inconsistent encryption** (only >1MB) - security inconsistency
- ❌ **No file integrity checks** (checksums/hashing)
- ❌ **Race condition** in storage quota check (upload.php lines 36-39)
- ❌ **No chunked uploads** - will fail for files >PHP memory limit
- ❌ **No concurrent upload protection**
- ❌ **No virus scanning**
- ❌ **No file type validation** - can upload .php, .exe files

**Solutions:**

#### 4.1 Physical File Deletion Cron
```php
// scripts/delete-old-files.php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$db = Database::getInstance()->getConnection();

// Get files deleted more than 30 days ago
$stmt = $db->prepare("
    SELECT id, file_path, file_size, user_id 
    FROM files 
    WHERE deleted_at IS NOT NULL 
    AND deleted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->execute();
$files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

foreach ($files as $file) {
    if (file_exists($file['file_path'])) {
        unlink($file['file_path']);
        echo "Deleted: {$file['file_path']}\n";
    }
    
    // Remove from database
    $stmt = $db->prepare("DELETE FROM files WHERE id = ?");
    $stmt->bind_param('i', $file['id']);
    $stmt->execute();
}

echo "Cleanup completed: " . count($files) . " files removed\n";
```

#### 4.2 Add File Integrity (SHA-256 Hash)
```sql
ALTER TABLE files ADD COLUMN file_hash VARCHAR(64) AFTER file_size;
CREATE INDEX idx_file_hash ON files(file_hash);
```

```php
// In upload.php, after move_uploaded_file:
$file_hash = hash_file('sha256', $file_path);

// Check for duplicates (deduplication)
$stmt = $db->prepare("SELECT id FROM files WHERE user_id = ? AND file_hash = ? AND deleted_at IS NULL");
$stmt->bind_param('is', $user_id, $file_hash);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    unlink($file_path);
    echo json_encode(['success' => false, 'message' => 'Duplicate file already exists']);
    exit;
}

// Store hash in database
```

#### 4.3 Fix Race Condition with Database Transaction
```php
// In upload.php, replace lines 36-39:
$db->begin_transaction();
try {
    // Lock user row
    $stmt = $db->prepare("SELECT storage_used FROM users WHERE id = ? FOR UPDATE");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['storage_used'] + $file['size'] > USER_STORAGE_QUOTA) {
        $db->rollback();
        echo json_encode(['success' => false, 'message' => 'Storage quota exceeded']);
        exit;
    }
    
    // ... proceed with upload ...
    
    $db->commit();
} catch (Exception $e) {
    $db->rollback();
    throw $e;
}
```

#### 4.4 File Type Validation
```php
// Add to upload.php before processing:
$allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
$file_ext = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));

if (!in_array($file_ext, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed']);
    exit;
}

// Validate MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_mimes = [
    'image/jpeg', 'image/png', 'image/gif',
    'application/pdf', 'text/plain',
    'application/msword', 'application/vnd.ms-excel'
];

if (!in_array($mime_type, $allowed_mimes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type']);
    exit;
}
```

---

### **5. Authentication Weaknesses** 🔐

**Issues:**
- ❌ **Rate limit check AFTER password verify** (should be before to prevent timing attacks)
- ❌ **No password reset** functionality
- ❌ **No email verification**
- ❌ **No 2FA/MFA support**
- ❌ **Login page has duplicate POST handling** (lines 1-21 and 144-155)
- ❌ **Session timeout not atomic** - race conditions
- ❌ **No account recovery mechanism**
- ❌ **Password minimum too weak** (6 chars)

**Solutions:**

#### 5.1 Fix Rate Limit Order (auth.php)
```php
// Move rate limit check BEFORE password verification (line ~77):
public function login($email, $password) {
    // Check rate limiting FIRST
    if (!$this->checkRateLimit($email)) {
        return ['success' => false, 'message' => 'Too many login attempts. Try again later.'];
    }
    
    $stmt = $this->db->prepare("SELECT id, username, password FROM users WHERE email = ?");
    // ... rest of login logic
}
```

#### 5.2 Password Reset Functionality
```sql
CREATE TABLE password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at)
);
```

```php
// public/forgot-password.php
public function requestPasswordReset($email) {
    $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Don't reveal if email exists (security)
        return ['success' => true, 'message' => 'If email exists, reset link sent'];
    }
    
    $user = $result->fetch_assoc();
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $stmt = $this->db->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param('iss', $user['id'], $token, $expires);
    $stmt->execute();
    
    // Send email (implement with PHPMailer)
    $reset_link = APP_URL . "/public/reset-password.php?token=" . $token;
    // mail($email, "Password Reset", "Click here: $reset_link");
    
    return ['success' => true, 'message' => 'Reset link sent'];
}
```

#### 5.3 Email Verification
```sql
ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE;
ALTER TABLE users ADD COLUMN verification_token VARCHAR(64);
```

#### 5.4 Increase Password Minimum (auth.php line 45)
```php
// Change from:
if (strlen($password) < 6) {
// To:
if (strlen($password) < 8) {
    return ['success' => false, 'message' => 'Password must be at least 8 characters'];
}

// Add password strength validation
if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
    return ['success' => false, 'message' => 'Password must contain uppercase, lowercase, and number'];
}
```

---

### **6. Architecture Anti-Patterns** 🏗️

**Issues:**
- ❌ **No MVC separation** - HTML mixed with PHP logic
- ❌ **Direct file access** - no front controller/routing
- ❌ **No dependency injection** - tight coupling
- ❌ **Global state** ($auth, $db variables everywhere)
- ❌ **No error logging** - just error_log() calls
- ❌ **No validation layer** - scattered validation
- ❌ **Hardcoded configuration** sprinkled throughout
- ❌ **Duplicate code** (decryptFile in 2 files)

**Solutions:**

#### 6.1 Extract Common Functions
```php
// includes/helpers.php
function decryptFile($encrypted_data, $key) {
    $iv = substr($encrypted_data, 0, 16);
    $encrypted = substr($encrypted_data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', hex2bin($key), OPENSSL_RAW_DATA, $iv);
}

function encryptFile($file_path, $key) {
    $data = file_get_contents($file_path);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($data, 'AES-256-CBC', hex2bin($key), OPENSSL_RAW_DATA, $iv);
    file_put_contents($file_path, $iv . $encrypted);
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $index = 0;
    while ($bytes >= 1024 && $index < count($units) - 1) {
        $bytes /= 1024;
        $index++;
    }
    return round($bytes, 2) . ' ' . $units[$index];
}

// Remove duplicate functions from download.php and preview-file.php
```

#### 6.2 Response Standardization
```php
// includes/response.php
class Response {
    public static function json($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    public static function success($message, $data = []) {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }
    
    public static function error($message, $status = 400) {
        self::json(['success' => false, 'message' => $message], $status);
    }
}

// Use in endpoints:
Response::error('File not found', 404);
Response::success('File uploaded successfully', ['file_id' => $file_id]);
```

#### 6.3 Validation Layer
```php
// includes/validator.php
class Validator {
    public static function email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function username($username) {
        return strlen($username) >= 3 && strlen($username) <= 50 
            && preg_match('/^[a-zA-Z0-9_]+$/', $username);
    }
    
    public static function password($password) {
        return strlen($password) >= 8 
            && preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password);
    }
    
    public static function fileSize($size) {
        return $size > 0 && $size <= MAX_FILE_SIZE;
    }
}
```

---

### **7. Missing Production Essentials** 🚀

**Issues:**
- ❌ **No monitoring/metrics** (Prometheus, NewRelic)
- ❌ **No structured logging**
- ❌ **No error tracking** (Sentry)
- ❌ **No database backups**
- ❌ **No connection pooling**
- ❌ **No caching layer** (Redis/Memcached)
- ❌ **No health check endpoints** (/health, /ready)
- ❌ **No graceful shutdown**
- ❌ **No rate limiting at nginx level**

**Solutions:**

#### 7.1 Health Check Endpoint
```php
// public/health.php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => time(),
    'checks' => []
];

// Database check
try {
    $db = Database::getInstance()->getConnection();
    $db->query('SELECT 1');
    $health['checks']['database'] = 'ok';
} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['database'] = 'failed: ' . $e->getMessage();
}

// Uploads directory check
$health['checks']['uploads_dir'] = is_writable(UPLOADS_DIR) ? 'ok' : 'not writable';
if (!is_writable(UPLOADS_DIR)) {
    $health['status'] = 'degraded';
}

// Disk space check
$free_space = disk_free_space(UPLOADS_DIR);
$total_space = disk_total_space(UPLOADS_DIR);
$health['checks']['disk_space'] = [
    'free' => round($free_space / 1024 / 1024 / 1024, 2) . ' GB',
    'percent_free' => round(($free_space / $total_space) * 100, 2)
];

http_response_code($health['status'] === 'healthy' ? 200 : 503);
echo json_encode($health, JSON_PRETTY_PRINT);
```

#### 7.2 Redis Integration
```yaml
# docker-compose.yml
services:
  redis:
    image: redis:7-alpine
    container_name: minicld_redis
    restart: always
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 3

volumes:
  redis_data:
```

```php
// includes/cache.php
class Cache {
    private static $redis = null;
    
    public static function connect() {
        if (self::$redis === null) {
            self::$redis = new Redis();
            self::$redis->connect('redis', 6379);
        }
        return self::$redis;
    }
    
    public static function get($key) {
        return self::connect()->get($key);
    }
    
    public static function set($key, $value, $ttl = 3600) {
        return self::connect()->setex($key, $ttl, $value);
    }
    
    public static function delete($key) {
        return self::connect()->del($key);
    }
}
```

#### 7.3 Structured Logging
```php
// includes/logger.php
class Logger {
    private static $log_file = '/var/www/html/logs/app.log';
    
    public static function log($level, $message, $context = []) {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = json_encode([
            'timestamp' => $timestamp,
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
        
        // Write to stdout for Docker
        error_log($log_entry);
        
        // Also write to file
        file_put_contents(self::$log_file, $log_entry . "\n", FILE_APPEND);
    }
    
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
}
```

#### 7.4 Database Backup Script
```bash
#!/bin/bash
# scripts/backup-database.sh

BACKUP_DIR="/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
FILENAME="mini_drive_$TIMESTAMP.sql"

mkdir -p $BACKUP_DIR

# Dump database
docker exec minicld_db mysqldump \
  -u mini_user \
  -pmini_pass \
  mini_drive > "$BACKUP_DIR/$FILENAME"

# Compress
gzip "$BACKUP_DIR/$FILENAME"

# Keep only last 7 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete

echo "Backup completed: $FILENAME.gz"
```

```bash
# Add to crontab:
0 3 * * * /var/www/html/scripts/backup-database.sh
```

---

### **8. Storage & Quota Issues** 💾

**Issues:**
- ❌ **Race condition** when checking quota (non-atomic)
- ❌ **Storage never actually reclaimed** from deleted files
- ❌ **No file deduplication** (same file uploaded twice counts double)
- ❌ **Upload limits per hour** reset abruptly instead of sliding window
- ❌ **No trash/recycle bin** - files immediately "deleted"

**Solutions:**

#### 8.1 Recycle Bin Implementation
```sql
-- Use existing soft delete as recycle bin
-- Add restore functionality

-- Add to index.php: trash section
SELECT * FROM files WHERE user_id = ? AND deleted_at IS NOT NULL ORDER BY deleted_at DESC
```

```php
// public/restore-file.php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

$auth = new Auth();
$auth->requireLogin();

$user_id = $auth->getCurrentUser();
$file_id = $_POST['file_id'] ?? 0;
$db = Database::getInstance()->getConnection();

$stmt = $db->prepare("UPDATE files SET deleted_at = NULL WHERE id = ? AND user_id = ?");
$stmt->bind_param('ii', $file_id, $user_id);

if ($stmt->execute()) {
    Response::success('File restored');
} else {
    Response::error('Restore failed');
}
```

#### 8.2 File Deduplication
```php
// After calculating file hash in upload.php:
$stmt = $db->prepare("
    SELECT id, filename FROM files 
    WHERE user_id = ? AND file_hash = ? AND deleted_at IS NULL
");
$stmt->bind_param('is', $user_id, $file_hash);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    unlink($file_path); // Remove duplicate
    echo json_encode([
        'success' => false, 
        'message' => 'File already exists: ' . $existing['filename']
    ]);
    exit;
}
```

#### 8.3 Sliding Window Rate Limit
```php
// Replace hour_key with sliding window (last 60 minutes)
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM files 
    WHERE user_id = ? 
    AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
    AND deleted_at IS NULL
");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

if ($result['count'] >= MAX_UPLOAD_PER_HOUR) {
    Response::error('Upload limit reached. Try again in a few minutes.');
}
```

---

### **9. Docker Production Gaps** 🐳

**Issues:**
- ❌ **www-data user set too early** - entrypoint can't create directories
- ❌ **No multi-stage builds** - larger image size
- ❌ **Missing restart: always** on app service
- ❌ **No resource limits** (memory, CPU)
- ❌ **Logs not properly configured for Docker**
- ❌ **No .env.example** file for reference

**Solutions:**

#### 9.1 Fix Dockerfile
```dockerfile
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Configure Apache
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's|<Directory /var/www/html>|<Directory /var/www/html/public>|g' /etc/apache2/apache2.conf \
 && sed -i 's|AllowOverride None|AllowOverride All|g' /etc/apache2/apache2.conf

# Create directories (as root)
RUN mkdir -p /var/www/html/uploads /var/www/html/logs \
 && chown -R www-data:www-data /var/www/html

# Copy entrypoint
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html/public

# Set entrypoint
ENTRYPOINT ["/entrypoint.sh"]

EXPOSE 80

# USER directive removed - entrypoint switches to www-data
```

#### 9.2 Improved docker-compose.yml
```yaml
version: '3.8'

services:
  app:
    build:
      context: ..
      dockerfile: docker/Dockerfile
    container_name: minicld_app
    restart: always
    ports:
      - "8080:80"
    volumes:
      - ../public:/var/www/html/public
      - ../includes:/var/www/html/includes
      - uploads_data:/var/www/html/uploads
      - logs_data:/var/www/html/logs
    environment:
      - DB_HOST=db
      - DB_USER=mini_user
      - DB_PASSWORD=mini_pass
      - DB_NAME=mini_drive
      - APP_ENV=production
    depends_on:
      db:
        condition: service_healthy
      redis:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health.php"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
    deploy:
      resources:
        limits:
          memory: 512M
          cpus: '0.5'
        reservations:
          memory: 256M
          cpus: '0.25'
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"

  db:
    image: mysql:8.0
    container_name: minicld_db
    restart: always
    environment:
      MYSQL_ROOT_PASSWORD: mini_pass
      MYSQL_DATABASE: mini_drive
      MYSQL_USER: mini_user
      MYSQL_PASSWORD: mini_pass
    ports:
      - "3307:3306"
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      timeout: 20s
      retries: 10
      interval: 10s
    deploy:
      resources:
        limits:
          memory: 1G

  redis:
    image: redis:7-alpine
    container_name: minicld_redis
    restart: always
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 3s
      retries: 3

volumes:
  db_data:
  uploads_data:
  redis_data:
  logs_data:
```

#### 9.3 Create .env.example
```bash
# .env.example
DB_HOST=db
DB_USER=mini_user
DB_PASSWORD=changeme_in_production
DB_NAME=mini_drive

APP_NAME=MiniDrive
APP_URL=http://localhost:8080
APP_ENV=production

MAX_FILE_SIZE=10485760
USER_STORAGE_QUOTA=52428800
SESSION_TIMEOUT=3600
MAX_UPLOAD_PER_HOUR=20

TZ=UTC
```

---

### **10. Code Quality & Maintainability** 🧹

**Issues:**
- ❌ **Duplicate code** (decryptFile function in 2 files)
- ❌ **No unit tests**
- ❌ **Inconsistent error responses** (some JSON, some headers)
- ❌ **No API documentation**
- ❌ **Magic numbers** (1048576 for 1MB, etc.)
- ❌ **No code linting/standards** (PSR-12)
- ❌ **Missing type hints** (PHP 7.4+ supports them)
- ❌ **Login.php has duplicate POST handling code**

**Solutions:**

#### 10.1 Remove Duplicates
```php
// Already covered in section 6.1 - extract to helpers.php
```

#### 10.2 Add PHPUnit Tests
```php
// tests/AuthTest.php
<?php
use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase {
    private $auth;
    
    protected function setUp(): void {
        $this->auth = new Auth();
    }
    
    public function testRegisterValidUser() {
        $result = $this->auth->register('testuser', 'test@example.com', 'Password123');
        $this->assertTrue($result['success']);
    }
    
    public function testRegisterWeakPassword() {
        $result = $this->auth->register('testuser', 'test@example.com', 'weak');
        $this->assertFalse($result['success']);
    }
    
    public function testLoginRateLimit() {
        // Test that 6th failed login is blocked
        for ($i = 0; $i < 6; $i++) {
            $result = $this->auth->login('test@example.com', 'wrongpassword');
        }
        $this->assertStringContainsString('Too many', $result['message']);
    }
}
```

```xml
<!-- phpunit.xml -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="tests/bootstrap.php"
         colors="true">
    <testsuites>
        <testsuite name="Application Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

#### 10.3 Fix login.php Duplicate Code
```php
// Remove lines 144-155 (duplicate POST handling at end of file)
// Keep only lines 1-21
```

#### 10.4 Add Type Hints
```php
// auth.php examples:
public function register(string $username, string $email, string $password): array
public function login(string $email, string $password): array
public function isLoggedIn(): bool
public function getCurrentUser(): ?int
```

#### 10.5 Define Constants for Magic Numbers
```php
// config.php additions:
define('BYTES_PER_KB', 1024);
define('BYTES_PER_MB', 1048576);
define('BYTES_PER_GB', 1073741824);
define('ENCRYPTION_THRESHOLD', 1 * BYTES_PER_MB); // 1MB

// Usage in upload.php:
if ($file['size'] > ENCRYPTION_THRESHOLD) {
    // encrypt
}
```

---

## **SPECIFIC FILE ISSUES**

### **config.php**
- **Line 55:** `error_reporting(E_ALL)` - Should check environment
- **Line 56:** `display_errors = 1` - **CRITICAL SECURITY RISK** in production
- **Missing:** Environment-based configuration switching

### **auth.php**
- **Line 91:** Rate limit check AFTER password verify (timing attack vulnerability)
- **Lines 108-120:** Session-based rate limiting (not persistent across sessions)
- **Line 95:** Missing `session_regenerate_id(true)` after successful login
- **Line 45:** Password minimum too weak (6 chars)

### **upload.php**
- **Lines 36-39:** Race condition in quota check (need database transaction)
- **Line 76:** `mkdir` without checking return value properly
- **Line 88:** Encryption key stored in database (insecure)
- **Line 94:** No file type/MIME validation
- **Line 137:** Encryption inconsistency (only >1MB files)

### **download.php & preview-file.php**
- **Duplicate `decryptFile()` function**
- **No bandwidth throttling** - DDoS vulnerability
- **No download count tracking**

### **delete-file.php**
- **Only soft deletes** - physical file never removed
- **Storage quota not reclaimed** - accumulates over time
- **No transaction** - file could be deleted from DB but remain on disk

### **index.php**
- **Lines 18-27:** Storage recalculation on EVERY page load (very expensive)
- **Line 42:** CSP header (good!) but should be in middleware for all pages
- **No pagination** - will break with 1000+ files

### **dashboard.js**
- **Line 171:** Client-side file size check (bypass-able, must validate server-side)
- **No upload retry logic** for failed uploads
- **No progress persistence** if page refreshes during upload

### **setup-db.php**
- **No checks** if tables already exist before showing "success"
- **Should use `IF NOT EXISTS`** (already does, good)
- **No seed data** for testing

### **docker/entrypoint.sh**
- **Line 19:** `exec apache2-foreground` - good, but should have signal handling
- **Missing:** Ownership fix for uploads directory
- **No cleanup** of old temporary files

---

## **PRIORITY FIX ROADMAP** 📋

### **Phase 1: Critical Security & Docker (Week 1)**
**Priority: URGENT**

- [ ] Rename `docker/DockerFile` to `docker/Dockerfile`
- [ ] Fix Dockerfile USER directive (remove or place after ENTRYPOINT)
- [ ] Create `.env` file and `.env.example`
- [ ] Add CSRF protection to all POST endpoints
- [ ] Implement persistent rate limiting (database/Redis)
- [ ] Add security headers middleware to all pages
- [ ] Fix session fixation (regenerate ID after login)
- [ ] Disable `display_errors` in production (config.php)
- [ ] Fix rate limit check order in auth.php (before password check)
- [ ] Add file type validation in upload.php

### **Phase 2: Data Integrity (Week 2)**
**Priority: HIGH**

- [ ] Add database transactions for quota checks
- [ ] Implement database triggers for storage calculation
- [ ] Create physical file deletion cron job
- [ ] Add database indexes for performance
- [ ] Store relative file paths instead of absolute
- [ ] Add file integrity checks (SHA-256 hashing)
- [ ] Implement file deduplication
- [ ] Fix storage reclamation on delete
- [ ] Remove storage recalculation from index.php

### **Phase 3: Production Readiness (Week 3)**
**Priority: MEDIUM**

- [ ] Add health check endpoints (/health.php)
- [ ] Implement structured logging
- [ ] Add Redis caching layer
- [ ] Setup database backup automation
- [ ] Add resource limits to docker-compose
- [ ] Implement proper error handling
- [ ] Create cleanup cron jobs
- [ ] Add graceful shutdown handling
- [ ] Fix login.php duplicate code

### **Phase 4: Features & Polish (Week 4)**
**Priority: NICE TO HAVE**

- [ ] Add password reset functionality
- [ ] Implement email verification
- [ ] Create trash/recycle bin feature
- [ ] Add file restore capability
- [ ] Implement pagination for file list
- [ ] Add download count tracking
- [ ] Create admin dashboard
- [ ] Add user activity logs

### **Phase 5: Operational Excellence (Ongoing)**
**Priority: CONTINUOUS**

- [ ] Add monitoring (Prometheus metrics)
- [ ] Implement error tracking (Sentry)
- [ ] Write unit tests (70% coverage target)
- [ ] Add API documentation (OpenAPI/Swagger)
- [ ] Setup CI/CD pipeline
- [ ] Add code linting (PHP-CS-Fixer)
- [ ] Implement static analysis (PHPStan)
- [ ] Create deployment documentation
- [ ] Security audit (OWASP ZAP)
- [ ] Load testing

---

## **IMMEDIATE DOCKER FIXES** 🔧

**Step-by-step to get running:**

```bash
# 1. Rename Dockerfile
cd d:/Project/Cloud-Drive/mini-drive
mv docker/DockerFile docker/Dockerfile

# 2. Create .env file
cat > .env << 'EOF'
DB_HOST=db
DB_USER=mini_user
DB_PASSWORD=mini_pass
DB_NAME=mini_drive
APP_URL=http://localhost:8080
APP_ENV=production
MAX_FILE_SIZE=10485760
USER_STORAGE_QUOTA=52428800
SESSION_TIMEOUT=3600
MAX_UPLOAD_PER_HOUR=20
TZ=UTC
EOF

# 3. Fix Dockerfile USER directive
# Edit docker/Dockerfile and remove or comment out:
# USER www-data
# OR move it to very end after ENTRYPOINT

# 4. Create logs directory
mkdir -p logs
chmod 777 logs

# 5. Build and run
cd docker
docker-compose down -v
docker-compose build --no-cache
docker-compose up -d

# 6. Check logs
docker-compose logs -f app

# 7. Verify health
curl http://localhost:8080/health.php

# 8. Access application
# Browser: http://localhost:8080
```

---

## **PRODUCTION DEPLOYMENT CHECKLIST** ✅

Before deploying to production:

### **Security**
- [ ] CSRF tokens on all forms
- [ ] Rate limiting implemented (persistent)
- [ ] Security headers enabled on all pages
- [ ] `display_errors` = 0
- [ ] `error_reporting` appropriate for production
- [ ] File upload validation (type, size, MIME)
- [ ] SQL injection prevention verified
- [ ] XSS prevention verified
- [ ] Password strength requirements enforced
- [ ] Session security configured (httponly, secure, samesite)

### **Docker & Infrastructure**
- [ ] `Dockerfile` renamed (not `DockerFile`)
- [ ] `.env` file created (not committed to git)
- [ ] Health checks configured
- [ ] Resource limits set
- [ ] Restart policies configured
- [ ] Logs configured (stdout/stderr)
- [ ] Volume persistence verified
- [ ] Backup strategy implemented

### **Database**
- [ ] Indexes created
- [ ] Triggers implemented
- [ ] Backup automation setup
- [ ] Transactions where needed
- [ ] Connection pooling configured
- [ ] Relative paths used (not absolute)

### **Monitoring & Logging**
- [ ] Health endpoints implemented
- [ ] Structured logging enabled
- [ ] Error tracking configured
- [ ] Metrics collection setup
- [ ] Alerting configured
- [ ] Log rotation enabled

### **Performance**
- [ ] Caching layer added (Redis)
- [ ] Database queries optimized
- [ ] Static assets cached
- [ ] Storage calculation optimized
- [ ] Pagination implemented
- [ ] CDN configured (if applicable)

### **Operations**
- [ ] Documentation complete
- [ ] Deployment procedure tested
- [ ] Rollback procedure documented
- [ ] Incident response plan created
- [ ] Access controls configured
- [ ] SSL/TLS certificates installed
- [ ] Firewall rules configured
- [ ] Security audit completed
- [ ] Load testing performed
- [ ] Disaster recovery tested

---

## **TESTING COMMANDS**

```bash
# Docker health
docker-compose ps
docker-compose logs app
docker exec minicld_app curl -f http://localhost/health.php

# Database connection
docker exec -it minicld_db mysql -u mini_user -p mini_drive

# File permissions
docker exec minicld_app ls -la /var/www/html/uploads

# Storage test
docker exec minicld_app df -h

# Application logs
docker exec minicld_app tail -f /var/www/html/logs/app.log
```

---

## **RECOMMENDED TOOLS & INTEGRATIONS**

### **Security**
- **OWASP ZAP** - Security testing
- **Snyk** - Dependency vulnerability scanning
- **Let's Encrypt** - Free SSL certificates

### **Monitoring**
- **Prometheus** - Metrics collection
- **Grafana** - Visualization
- **Sentry** - Error tracking
- **NewRelic / DataDog** - APM

### **Performance**
- **Redis** - Caching & sessions
- **Nginx** - Reverse proxy & rate limiting
- **Varnish** - HTTP caching

### **Development**
- **PHPUnit** - Unit testing
- **PHP-CS-Fixer** - Code formatting
- **PHPStan** - Static analysis
- **Xdebug** - Debugging

### **DevOps**
- **GitHub Actions** - CI/CD
- **Docker Hub** - Image registry
- **Portainer** - Docker management
- **Watchtower** - Auto-update containers

---

## **SUMMARY**

### **Current State**
⚠️ **NOT Production Ready** - Multiple critical issues identified

### **Main Problems**
1. **Docker misconfiguration** preventing proper startup
2. **Security vulnerabilities** requiring immediate attention
3. **Data integrity issues** causing storage leaks
4. **Missing production essentials** (monitoring, logging, backups)
5. **Architecture limitations** affecting maintainability

### **Effort Estimate**
- **Phase 1 (Critical):** 40 hours
- **Phase 2 (High Priority):** 35 hours
- **Phase 3 (Production Ready):** 30 hours
- **Phase 4 (Features):** 25 hours
- **Total:** ~130 hours (3-4 weeks full-time)

### **Risk Assessment**
- **Current Risk:** **HIGH** - Should not be used in production
- **After Phase 1:** **MEDIUM** - Can be used with caution
- **After Phase 3:** **LOW** - Production ready with monitoring

---

**Last Updated:** February 9, 2026  
**Next Review:** After completing Phase 1 fixes
