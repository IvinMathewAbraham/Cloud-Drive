<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = Database::getInstance()->getConnection();

$cleanupStatements = [
    'SET FOREIGN_KEY_CHECKS = 0',
    'DROP TABLE IF EXISTS photo_sharing',
    'DROP TABLE IF EXISTS photos',
    'DROP TABLE IF EXISTS albums',
    'DROP TABLE IF EXISTS file_sharing',
    'DROP TABLE IF EXISTS files',
    'DROP TABLE IF EXISTS upload_rate_limit',
    'SET FOREIGN_KEY_CHECKS = 1'
];

foreach ($cleanupStatements as $sql) {
    if (!$db->query($sql)) {
        echo "Cleanup step failed: " . $db->error() . "\n";
    }
}

$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        storage_used BIGINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS albums (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        parent_id INT DEFAULT NULL,
        name VARCHAR(120) NOT NULL,
        path VARCHAR(512) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (parent_id) REFERENCES albums(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_user_path (user_id, path),
        INDEX idx_user_parent (user_id, parent_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS photos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        album_id INT NOT NULL,
        storage_key VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        mime_type VARCHAR(100) NOT NULL,
        file_size BIGINT NOT NULL,
        width INT DEFAULT NULL,
        height INT DEFAULT NULL,
        exif_taken_at DATETIME DEFAULT NULL,
        exif_camera_make VARCHAR(120) DEFAULT NULL,
        exif_camera_model VARCHAR(120) DEFAULT NULL,
        exif_focal_length VARCHAR(50) DEFAULT NULL,
        exif_aperture VARCHAR(50) DEFAULT NULL,
        exif_iso INT DEFAULT NULL,
        checksum CHAR(64) NOT NULL,
        thumbnail_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
        UNIQUE KEY uniq_user_checksum (user_id, checksum),
        INDEX idx_album_deleted (album_id, deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS photo_sharing (
        id INT PRIMARY KEY AUTO_INCREMENT,
        photo_id INT NOT NULL,
        shared_with_user_id INT NOT NULL,
        permission ENUM('viewer', 'editor') DEFAULT 'viewer',
        shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (photo_id) REFERENCES photos(id) ON DELETE CASCADE,
        FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_share (photo_id, shared_with_user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

    "CREATE TABLE IF NOT EXISTS ingest_rate_limit (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        ingest_count INT DEFAULT 0,
        hour_key VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_hour_limit (user_id, hour_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $table) {
    if (!$db->query($table)) {
        echo "Error creating table: " . $db->error() . "\n";
    }
}

$users = $db->query("SELECT id FROM users");
if ($users instanceof mysqli_result) {
    $createAlbumStmt = $db->prepare(
        "INSERT INTO albums (user_id, parent_id, name, path) VALUES (?, NULL, ?, ?)"
    );
    $albumExistsStmt = $db->prepare(
        "SELECT id FROM albums WHERE user_id = ? AND parent_id IS NULL LIMIT 1"
    );

    if ($createAlbumStmt && $albumExistsStmt) {
        $rootName = 'Library';
        while ($row = $users->fetch_assoc()) {
            $userId = (int)$row['id'];

            $albumExistsStmt->bind_param('i', $userId);
            if ($albumExistsStmt->execute()) {
                $result = $albumExistsStmt->get_result();
                $hasRoot = $result && $result->num_rows > 0;
                if ($result instanceof mysqli_result) {
                    $result->free();
                }

                if (!$hasRoot) {
                    $path = '/';
                    $createAlbumStmt->bind_param('iss', $userId, $rootName, $path);
                    if (!$createAlbumStmt->execute()) {
                        echo "Failed to seed root album for user {$userId}: " . $createAlbumStmt->error . "\n";
                    }
                }
            } else {
                echo "Failed to check root album for user {$userId}: " . $albumExistsStmt->error . "\n";
            }
        }
    }

    if ($albumExistsStmt instanceof mysqli_stmt) {
        $albumExistsStmt->close();
    }
    if ($createAlbumStmt instanceof mysqli_stmt) {
        $createAlbumStmt->close();
    }
}

echo "PhotoDrive database objects are ready!\n";
?>
