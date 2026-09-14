<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\ImageRepository;
use App\Support\ValidationException;

/**
 * Decodes, re-encodes and stores uploaded images.
 *
 * Validation is done on the server against the decoded bytes, never on the
 * client-supplied MIME type or extension. Re-encoding also strips metadata.
 */
class ImageService
{
    private const ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

    private ImageRepository $images;

    private array $config;

    public function __construct(ImageRepository $images, array $config)
    {
        $this->images = $images;
        $this->config = $config;

        if (! is_dir($config['path'])) {
            mkdir($config['path'], 0775, true);
        }
    }

    /**
     * @param  array  $uploads  normalised $_FILES entries
     * @return array  created image rows
     */
    public function store(int $testimonialId, array $uploads, int $startOrder): array
    {
        $created = [];
        $writtenFiles = [];

        try {
            foreach ($uploads as $index => $upload) {
                $row = $this->processOne($testimonialId, $upload, $startOrder + $index);
                $writtenFiles[] = $row['path'];
                $writtenFiles[] = $row['thumbnail_path'];
                $created[] = $row;
            }
        } catch (\Throwable $e) {
            // Compensate for partial writes so no orphan files remain on disk.
            foreach ($writtenFiles as $file) {
                $this->deleteFile($file);
            }

            throw $e;
        }

        return $created;
    }

    private function processOne(int $testimonialId, array $upload, int $sortOrder): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException(['images' => [$this->uploadErrorMessage((int) $upload['error'])]]);
        }

        if (! is_uploaded_file($upload['tmp_name']) && ! is_file($upload['tmp_name'])) {
            throw new ValidationException(['images' => ['The upload could not be read.']]);
        }

        if ((int) $upload['size'] > $this->config['max_bytes']) {
            throw new ValidationException(['images' => [sprintf('Each image must be %d MB or smaller.', (int) ($this->config['max_bytes'] / 1048576))]]);
        }

        $info = @getimagesize($upload['tmp_name']);

        if ($info === false || ! in_array($info['mime'], self::ALLOWED, true)) {
            throw new ValidationException(['images' => ['Only JPG, PNG and WebP images are accepted.']]);
        }

        [$width, $height] = $info;

        if ($width * $height > $this->config['max_pixels']) {
            throw new ValidationException(['images' => ['The image resolution is too large.']]);
        }

        $source = $this->decode($upload['tmp_name'], $info['mime']);

        if ($source === null) {
            throw new ValidationException(['images' => ['The image could not be decoded.']]);
        }

        try {
            $full = $this->resize($source, $this->config['max_dimension'], $this->config['max_dimension'], false);
            // Thumbnails are cropped so the grid is not ragged: every one comes
            // out at exactly thumb_width x thumb_height.
            $thumb = $this->resize($source, $this->config['thumb_width'], $this->config['thumb_height'], true);

            $useWebp = function_exists('imagewebp');
            $extension = $useWebp ? 'webp' : 'png';
            $name = $this->uuid();
            $path = $name.'.'.$extension;
            $thumbPath = $name.'-thumb.'.$extension;

            $this->write($full, $path, $useWebp);
            $this->write($thumb, $thumbPath, $useWebp);

            $row = [
                'testimonial_id' => $testimonialId,
                'path' => $path,
                'thumbnail_path' => $thumbPath,
                // The original name is metadata only; it never touches the filesystem.
                'original_filename' => mb_substr(basename((string) $upload['name']), 0, 255),
                'mime_type' => $useWebp ? 'image/webp' : 'image/png',
                'size' => filesize($this->config['path'].'/'.$path) ?: 0,
                'width' => imagesx($full),
                'height' => imagesy($full),
                'sort_order' => $sortOrder,
            ];

            imagedestroy($full);
            imagedestroy($thumb);

            $row['id'] = $this->images->create($row);

            return $row;
        } finally {
            imagedestroy($source);
        }
    }

    private function decode(string $file, string $mime)
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($file),
            'image/png' => @imagecreatefrompng($file),
            'image/webp' => @imagecreatefromwebp($file),
            default => false,
        };

        return $image === false ? null : $image;
    }

    /**
     * Scales an image down.
     *
     * With $crop false the whole image is kept and the aspect ratio decides the
     * result, never larger than the given box. With $crop true the result is
     * exactly $maxWidth x $maxHeight: the largest centred region of the source
     * with that aspect ratio is taken and scaled to fill the box.
     */
    private function resize($source, int $maxWidth, int $maxHeight, bool $crop)
    {
        $width = imagesx($source);
        $height = imagesy($source);

        if ($crop) {
            // Widest or tallest centred rectangle that matches the target ratio.
            $ratio = $maxWidth / $maxHeight;
            $cropWidth = min($width, (int) round($height * $ratio));
            $cropHeight = min($height, (int) round($width / $ratio));
            $srcX = (int) round(($width - $cropWidth) / 2);
            $srcY = (int) round(($height - $cropHeight) / 2);

            $canvas = $this->canvas($maxWidth, $maxHeight);
            imagecopyresampled($canvas, $source, 0, 0, $srcX, $srcY, $maxWidth, $maxHeight, max(1, $cropWidth), max(1, $cropHeight));

            return $canvas;
        }

        $scale = min($maxWidth / $width, $maxHeight / $height, 1);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = $this->canvas($targetWidth, $targetHeight);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    /** A transparent truecolour canvas that keeps the alpha channel on save. */
    private function canvas(int $width, int $height)
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));

        return $canvas;
    }

    private function write($image, string $path, bool $useWebp): void
    {
        $target = $this->config['path'].'/'.$path;

        $ok = $useWebp ? imagewebp($image, $target, 82) : imagepng($image, $target, 6);

        if (! $ok) {
            throw new \RuntimeException('Could not write the image to storage.');
        }
    }

    public function deleteFile(string $path): void
    {
        $full = $this->config['path'].'/'.basename($path);

        if (is_file($full)) {
            @unlink($full);
        }
    }

    public function absolutePath(string $path): string
    {
        return $this->config['path'].'/'.basename($path);
    }

    public function copyFile(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $target = $this->uuid().'.'.$extension;

        if (! copy($this->absolutePath($path), $this->config['path'].'/'.$target)) {
            throw new \RuntimeException('Could not copy the image file.');
        }

        return $target;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The image is larger than the server allows.',
            UPLOAD_ERR_PARTIAL => 'The image was only partially uploaded.',
            UPLOAD_ERR_NO_FILE => 'No image was uploaded.',
            default => 'The image could not be uploaded.',
        };
    }
}
