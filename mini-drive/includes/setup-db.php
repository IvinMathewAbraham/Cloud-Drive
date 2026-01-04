<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$db = Database::getInstance()->getConnection();

// Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        storage_used BIGINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS files (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        file_size BIGINT NOT NULL,
        file_type VARCHAR(100),
        folder_path VARCHAR(255) DEFAULT '/',
        is_encrypted BOOLEAN DEFAULT FALSE,
        encryption_key VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL DEFAULT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_deleted (user_id, deleted_at),
        INDEX idx_user_folder (user_id, folder_path(100))
    )",

    "CREATE TABLE IF NOT EXISTS file_sharing (
        id INT PRIMARY KEY AUTO_INCREMENT,
        file_id INT NOT NULL,
        shared_with_user_id INT NOT NULL,
        permission ENUM('viewer', 'editor') DEFAULT 'viewer',
        shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (file_id) REFERENCES files(id) ON DELETE CASCADE,
        FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_share (file_id, shared_with_user_id)
    )",

    "CREATE TABLE IF NOT EXISTS upload_rate_limit (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        upload_count INT DEFAULT 0,
        hour_key VARCHAR(20) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY unique_hour_limit (user_id, hour_key)
    )"
];

foreach ($tables as $table) {
    if (!$db->query($table)) {
        echo "Error creating table: " . $db->error() . "\n";
    }
}

echo "Database tables created successfully!\n";
?>
