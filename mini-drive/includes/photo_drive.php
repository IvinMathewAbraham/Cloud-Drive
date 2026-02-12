<?php
require_once __DIR__ . '/db.php';

class AlbumService
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    public function ensureRootAlbum(int $userId): array
    {
        $stmt = $this->conn->prepare('SELECT * FROM albums WHERE user_id = ? AND parent_id IS NULL LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }

        $name = 'Library';
        $path = '/';
        $insert = $this->conn->prepare('INSERT INTO albums (user_id, parent_id, name, path) VALUES (?, NULL, ?, ?)');
        $insert->bind_param('iss', $userId, $name, $path);
        if (!$insert->execute()) {
            throw new RuntimeException('Failed to seed root album: ' . $insert->error);
        }
        $id = $this->conn->insert_id;
        return ['id' => $id, 'user_id' => $userId, 'parent_id' => null, 'name' => $name, 'path' => $path];
    }

    public function getAlbumForUser(int $albumId, int $userId): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM albums WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->bind_param('ii', $albumId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result && $result->num_rows === 1 ? $result->fetch_assoc() : null;
    }

    public function createAlbum(int $userId, string $name, int $parentAlbumId): array
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 120) {
            throw new InvalidArgumentException('Album name must be 1-120 characters');
        }
        if (preg_match('/[\\\/<>:*?"|]/', $name)) {
            throw new InvalidArgumentException('Album name contains invalid characters');
        }

        $parent = $this->getAlbumForUser($parentAlbumId, $userId);
        if (!$parent) {
            throw new RuntimeException('Parent album not found');
        }

        $path = $this->buildPath($parent['path'], $name);

        // Ensure unique name under parent
        $dupe = $this->conn->prepare('SELECT id FROM albums WHERE user_id = ? AND parent_id = ? AND name = ? LIMIT 1');
        $dupe->bind_param('iis', $userId, $parentAlbumId, $name);
        $dupe->execute();
        $dupeResult = $dupe->get_result();
        if ($dupeResult && $dupeResult->num_rows > 0) {
            throw new RuntimeException('Album with this name already exists');
        }

        $stmt = $this->conn->prepare('INSERT INTO albums (user_id, parent_id, name, path) VALUES (?, ?, ?, ?)');
        $stmt->bind_param('iiss', $userId, $parentAlbumId, $name, $path);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to create album: ' . $stmt->error);
        }

        $id = $this->conn->insert_id;
        return [
            'id' => $id,
            'user_id' => $userId,
            'parent_id' => $parentAlbumId,
            'name' => $name,
            'path' => $path,
        ];
    }

    public function getAlbumUsageBytes(int $albumId): int
    {
        $stmt = $this->conn->prepare('SELECT COALESCE(SUM(file_size), 0) AS usage_bytes FROM photos WHERE album_id = ? AND deleted_at IS NULL');
        $stmt->bind_param('i', $albumId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $row = $result->fetch_assoc()) {
            return (int)$row['usage_bytes'];
        }
        return 0;
    }

    public function touchAlbum(int $albumId): void
    {
        $stmt = $this->conn->prepare('UPDATE albums SET updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->bind_param('i', $albumId);
        $stmt->execute();
    }

    private function buildPath(string $parentPath, string $name): string
    {
        if ($parentPath === '/') {
            return '/' . $name;
        }
        $trimmed = rtrim($parentPath, '/');
        return $trimmed . '/' . $name;
    }
}

class DuplicatePhotoException extends RuntimeException
{
}

class PhotoService
{
    private $conn;

    public function __construct()
    {
        $this->conn = Database::getInstance()->getConnection();
    }

    public function insertPhoto(array $payload): int
    {
        $stmt = $this->conn->prepare(
            'INSERT INTO photos (user_id, album_id, storage_key, original_filename, mime_type, file_size, width, height, exif_taken_at, exif_camera_make, exif_camera_model, exif_focal_length, exif_aperture, exif_iso, checksum, thumbnail_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        $userId = $payload['user_id'];
        $albumId = $payload['album_id'];
        $storageKey = $payload['storage_key'];
        $originalName = $payload['original_filename'];
        $mimeType = $payload['mime_type'];
        $fileSize = $payload['file_size'];
        $width = $payload['width'];
        $height = $payload['height'];
        $takenAt = $payload['exif_taken_at'];
        $cameraMake = $payload['exif_camera_make'];
        $cameraModel = $payload['exif_camera_model'];
        $focalLength = $payload['exif_focal_length'];
        $aperture = $payload['exif_aperture'];
        $iso = $payload['exif_iso'];
        $checksum = $payload['checksum'];
        $thumbnailPath = $payload['thumbnail_path'];

        $stmt->bind_param(
            'iisssiiissssssss',
            $userId,
            $albumId,
            $storageKey,
            $originalName,
            $mimeType,
            $fileSize,
            $width,
            $height,
            $takenAt,
            $cameraMake,
            $cameraModel,
            $focalLength,
            $aperture,
            $iso,
            $checksum,
            $thumbnailPath
        );

        if (!$stmt->execute()) {
            if ($stmt->errno === 1062) {
                throw new DuplicatePhotoException('Duplicate photo detected');
            }
            throw new RuntimeException('Failed to store photo metadata: ' . $stmt->error);
        }

        return $this->conn->insert_id;
    }

    public function getOwnedPhoto(int $photoId, int $userId): ?array
    {
        $stmt = $this->conn->prepare('SELECT * FROM photos WHERE id = ? AND user_id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->bind_param('ii', $photoId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result && $result->num_rows === 1 ? $result->fetch_assoc() : null;
    }

    public function updateUserStorage(int $userId, int $bytesDelta): void
    {
        $stmt = $this->conn->prepare('UPDATE users SET storage_used = GREATEST(storage_used + ?, 0) WHERE id = ?');
        $stmt->bind_param('ii', $bytesDelta, $userId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to update storage usage');
        }
    }

    public function softDeletePhoto(int $photoId, int $userId): ?array
    {
        $photo = $this->getOwnedPhoto($photoId, $userId);
        if (!$photo) {
            return null;
        }

        $stmt = $this->conn->prepare('UPDATE photos SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->bind_param('i', $photoId);
        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to delete photo: ' . $stmt->error);
        }

        return [
            'file_size' => (int)$photo['file_size'],
            'storage_key' => $photo['storage_key'],
            'thumbnail_path' => $photo['thumbnail_path'],
        ];
    }

    public function movePhoto(int $photoId, int $userId, int $targetAlbumId): bool
    {
        $photo = $this->getOwnedPhoto($photoId, $userId);
        if (!$photo) {
            return false;
        }

        $stmt = $this->conn->prepare('UPDATE photos SET album_id = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
        $stmt->bind_param('ii', $targetAlbumId, $photoId);
        return $stmt->execute();
    }

    public function checksumExists(int $userId, string $checksum): bool
    {
        $stmt = $this->conn->prepare('SELECT id FROM photos WHERE user_id = ? AND checksum = ? LIMIT 1');
        $stmt->bind_param('is', $userId, $checksum);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result && $result->num_rows > 0;
    }
}
?>
