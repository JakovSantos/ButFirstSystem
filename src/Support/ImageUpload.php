<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\App;

/**
 * Handles the "Browse Image" upload (replaces the C# OpenFileDialog + local file path).
 * Files are stored in public/uploads/products and the relative path is saved in image_path.
 */
final class ImageUpload
{
    /**
     * @param array<string, mixed>|null $file  one entry of $_FILES
     * @return array{path: ?string, error: ?string}
     */
    public static function store(?array $file): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return ['path' => null, 'error' => null];
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['path' => null, 'error' => 'The selected file could not be uploaded.'];
        }

        $max = (int) App::config('uploads.max_bytes', 5 * 1024 * 1024);
        if ((int) $file['size'] > $max) {
            return ['path' => null, 'error' => 'The selected image is larger than 5 MB.'];
        }

        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $allowed = (array) App::config('uploads.extensions', ['jpg', 'jpeg', 'png', 'bmp']);
        if (! in_array($ext, $allowed, true)) {
            return ['path' => null, 'error' => 'Image Files (*.jpg; *.jpeg; *.png; *.bmp) only.'];
        }

        $info = @getimagesize((string) $file['tmp_name']);
        if ($info === false) {
            return ['path' => null, 'error' => 'The selected file is not a valid image.'];
        }

        $base = preg_replace('/[^a-z0-9]+/', '-', strtolower(pathinfo((string) $file['name'], PATHINFO_FILENAME))) ?: 'image';
        $name = trim($base, '-') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dir = (string) App::config('uploads.dir');

        if (! is_uploaded_file((string) $file['tmp_name'])
            || ! move_uploaded_file((string) $file['tmp_name'], $dir . '/' . $name)) {
            return ['path' => null, 'error' => 'The selected file could not be saved.'];
        }

        return ['path' => ltrim((string) App::config('uploads.url'), '/') . '/' . $name, 'error' => null];
    }
}
