<?php
require_once __DIR__ . '/config.php';

class Media
{
    private const SUPPORTED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp'
    ];

    public static function validateImageUpload(array $file): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload error'];
        }

        if ($file['size'] > PHOTO_MAX_SIZE) {
            return ['success' => false, 'message' => 'Photo exceeds max size'];
        }

        $mime = self::detectMime($file['tmp_name']);
        if (!$mime || !in_array($mime, self::SUPPORTED_MIME_TYPES, true)) {
            return ['success' => false, 'message' => 'Unsupported image type'];
        }

        return ['success' => true, 'mime' => $mime];
    }

    public static function detectMime(string $path): ?string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            return null;
        }
        $mime = finfo_file($finfo, $path) ?: null;
        finfo_close($finfo);
        return $mime;
    }

    public static function generateStorageKey(int $userId, string $extension): string
    {
        $token = bin2hex(random_bytes(8));
        return "users/{$userId}/photos/" . $token . '.' . strtolower($extension);
    }

    public static function generateThumbnailKey(int $userId, string $extension): string
    {
        $token = bin2hex(random_bytes(8));
        return "users/{$userId}/thumbnails/" . $token . '.' . strtolower($extension);
    }

    public static function ensureDirectory(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    public static function moveUpload(string $tmpPath, string $targetPath): bool
    {
        self::ensureDirectory($targetPath);
        return move_uploaded_file($tmpPath, $targetPath);
    }

    public static function createThumbnail(string $sourcePath, string $targetPath, int $maxWidth = 512, int $maxHeight = 512): bool
    {
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        [$width, $height, $type] = $imageInfo;
        if ($width <= $maxWidth && $height <= $maxHeight) {
            // Small enough, just copy
            self::ensureDirectory($targetPath);
            return copy($sourcePath, $targetPath);
        }

        $ratio = min($maxWidth / $width, $maxHeight / $height);
        $newWidth = (int)($width * $ratio);
        $newHeight = (int)($height * $ratio);

        $srcImage = self::createImageResource($sourcePath, $type);
        if ($srcImage === null) {
            return false;
        }

        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumbnail, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        self::ensureDirectory($targetPath);
        $result = self::saveImageResource($thumbnail, $targetPath, $type);

        imagedestroy($thumbnail);
        imagedestroy($srcImage);

        return $result;
    }

    private static function createImageResource(string $path, int $type)
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg($path);
            case IMAGETYPE_PNG:
                return imagecreatefrompng($path);
            case IMAGETYPE_GIF:
                return imagecreatefromgif($path);
            case IMAGETYPE_WEBP:
                return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : null;
            default:
                return null;
        }
    }

    private static function saveImageResource($resource, string $path, int $type): bool
    {
        switch ($type) {
            case IMAGETYPE_JPEG:
                return imagejpeg($resource, $path, 85);
            case IMAGETYPE_PNG:
                return imagepng($resource, $path, 6);
            case IMAGETYPE_GIF:
                return imagegif($resource, $path);
            case IMAGETYPE_WEBP:
                return function_exists('imagewebp') ? imagewebp($resource, $path, 85) : false;
            default:
                return false;
        }
    }

    public static function extractImageMetadata(string $path): array
    {
        $dimensions = getimagesize($path);
        $meta = [
            'width' => $dimensions[0] ?? null,
            'height' => $dimensions[1] ?? null,
            'exif_taken_at' => null,
            'exif_camera_make' => null,
            'exif_camera_model' => null,
            'exif_focal_length' => null,
            'exif_aperture' => null,
            'exif_iso' => null,
        ];

        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($path, 'ANY_TAG', true);
            if ($exif && isset($exif['IFD0'])) {
                $meta['exif_camera_make'] = $exif['IFD0']['Make'] ?? null;
                $meta['exif_camera_model'] = $exif['IFD0']['Model'] ?? null;
            }
            if ($exif && isset($exif['EXIF'])) {
                if (!empty($exif['EXIF']['DateTimeOriginal'])) {
                    $meta['exif_taken_at'] = date('Y-m-d H:i:s', strtotime($exif['EXIF']['DateTimeOriginal']));
                }
                $meta['exif_focal_length'] = $exif['EXIF']['FocalLength'] ?? null;
                $meta['exif_aperture'] = $exif['EXIF']['FNumber'] ?? null;
                $meta['exif_iso'] = isset($exif['EXIF']['ISOSpeedRatings']) ? (int)$exif['EXIF']['ISOSpeedRatings'] : null;
            }
        }

        return $meta;
    }
}
?>
