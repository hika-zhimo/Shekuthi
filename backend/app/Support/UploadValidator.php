<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Single shared image-upload validator (M2.3): allow-listed types, size and
 * dimension caps, and GD re-encoding. Every upload path — product images,
 * UPI QR (M6.2), verification evidence (M5.2) — goes through this class;
 * change it here only.
 *
 * Uploads are normalised to WebP by default (M11.1) at a fixed quality:
 * decoded with GD, **downscaled to a maximum long side (M30.1)** and
 * re-encoded in one pass, which also strips any embedded payloads.
 * Transparent PNG/WebP sources keep their alpha channel, and the original
 * file is never stored. Callers whose image must stay lossless can pass
 * `$convertToWebp: false` (the UPI QR) — that path is re-encoded in its
 * original format instead. If the host's GD cannot encode WebP the verified
 * original is moved, so uploads never fail over a conversion.
 */
final class UploadValidator
{
    /** Allowed source MIME types (verified by content, not client header). */
    public const ALLOWED_MIMES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public const MAX_KILOBYTES = 2048; // 2 MB

    public const EVIDENCE_MAX_KILOBYTES = 500;

    /** Reject absurd source dimensions before GD decodes them. */
    public const MAX_DIMENSION = 4096;

    /**
     * Stored images are downscaled so the long side is at most this many
     * pixels. Photos come off phones at 3000–4000 px; 1600 px is ample for
     * listing cards and detail views and roughly halves the encoded bytes.
     */
    public const MAX_OUTPUT_DIMENSION = 1600;

    /** WebP quality for re-encoding: visually lossless for listings. */
    public const WEBP_QUALITY = 82;

    /**
     * @return array{path: string, mime: string, size: int} path is relative
     *                                                      to the given disk directory (e.g. "products/abc123.webp").
     *
     * @throws ValidationException
     */
    public static function validateAndStore(
        UploadedFile $file,
        string $directory,
        bool $convertToWebp = true,
    ): array {
        $maxKilobytes = $directory === 'evidence' ? self::EVIDENCE_MAX_KILOBYTES : self::MAX_KILOBYTES;
        if (! $file->isValid()) {
            throw ValidationException::withMessages([
                'file' => ['The upload failed. Try again.'],
            ]);
        }

        if ($file->getSize() > $maxKilobytes * 1024) {
            throw ValidationException::withMessages([
                'file' => ["Images may be at most {$maxKilobytes} KB."],
            ]);
        }

        // Trust the detected content type, never the client-supplied one.
        $mime = self::detectMime($file);

        if ($mime === null || ! isset(self::ALLOWED_MIMES[$mime])) {
            throw ValidationException::withMessages([
                'file' => ['Only JPEG, PNG or WebP images are allowed.'],
            ]);
        }

        $dimensions = @getimagesize($file->getRealPath());

        if ($dimensions === false) {
            throw ValidationException::withMessages([
                'file' => ['The file is not a readable image.'],
            ]);
        }

        [$width, $height] = $dimensions;

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw ValidationException::withMessages([
                'file' => ['Images may be at most '.self::MAX_DIMENSION.' pixels on a side.'],
            ]);
        }

        $stored = self::store($file, $directory, $mime, $convertToWebp);

        try {
            // Flysystem clears its own stat cache, so this reflects the file
            // just written (native filesize can read a stale zero here).
            $storedSize = (int) \Storage::disk('public')->size($stored['path']);
        } catch (\Throwable) {
            $storedSize = (int) $file->getSize();
        }

        if ($directory === 'evidence' && $storedSize > $maxKilobytes * 1024) {
            \Storage::disk('public')->delete($stored['path']);
            throw ValidationException::withMessages([
                'file' => ["The processed image must be at most {$maxKilobytes} KB. Choose a smaller image."],
            ]);
        }

        return [
            'path' => $stored['path'],
            'mime' => $stored['mime'],
            'size' => $storedSize > 0 ? $storedSize : (int) $file->getSize(),
        ];
    }

    private static function detectMime(UploadedFile $file): ?string
    {
        $info = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $info !== false ? (string) finfo_file($info, $file->getRealPath()) : '';
        finfo_close($info);

        return $mime !== '' ? $mime : null;
    }

    /**
     * Re-encode the upload — WebP by default, original format otherwise.
     * Falls back to a plain move when the host's GD cannot encode it.
     *
     * @return array{path: string, mime: string}
     */
    private static function store(
        UploadedFile $file,
        string $directory,
        string $mime,
        bool $convertToWebp,
    ): array {
        if (function_exists('imagecreatefromstring')) {
            $source = @file_get_contents($file->getRealPath());
            $image = $source !== false ? @imagecreatefromstring($source) : false;

            if ($image !== false) {
                $image = self::downscale($image);

                $stored = $convertToWebp && function_exists('imagewebp')
                    ? self::storeWebp($image, $directory)
                    : self::storeOriginalFormat($image, $directory, $mime);

                imagedestroy($image);

                if ($stored !== null) {
                    return $stored;
                }
            }
        }

        // Fallback: move without re-encoding (content type already verified).
        $name = bin2hex(random_bytes(16)).'.'.self::ALLOWED_MIMES[$mime];

        return [
            'path' => $file->storeAs($directory, $name, 'public'),
            'mime' => $mime,
        ];
    }

    /**
     * @return array{path: string, mime: string}|null
     */
    private static function storeWebp(\GdImage $image, string $directory): ?array
    {
        $relative = $directory.'/'.bin2hex(random_bytes(16)).'.webp';
        $target = \Storage::disk('public')->path($relative);
        \Storage::disk('public')->makeDirectory($directory);

        self::prepareForEncoding($image);

        if (! @imagewebp($image, $target, self::WEBP_QUALITY)) {
            @unlink($target);

            return null;
        }

        return ['path' => $relative, 'mime' => 'image/webp'];
    }

    /**
     * Re-encode in the source format (lossless for PNG). Used for images
     * that must not be lossily converted, e.g. the UPI QR.
     *
     * @return array{path: string, mime: string}|null
     */
    private static function storeOriginalFormat(\GdImage $image, string $directory, string $mime): ?array
    {
        $relative = $directory.'/'.bin2hex(random_bytes(16)).'.'.self::ALLOWED_MIMES[$mime];
        $target = \Storage::disk('public')->path($relative);
        \Storage::disk('public')->makeDirectory($directory);

        self::prepareForEncoding($image);

        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($image, $target, 85),
            'image/png' => imagepng($image, $target, 6),
            'image/webp' => imagewebp($image, $target, 85),
            default => false,
        };

        if (! $ok) {
            @unlink($target);

            return null;
        }

        return ['path' => $relative, 'mime' => $mime];
    }

    /**
     * Scale the image down so its long side is at most MAX_OUTPUT_DIMENSION,
     * preserving aspect ratio. Smaller sources are returned untouched.
     */
    private static function downscale(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= self::MAX_OUTPUT_DIMENSION) {
            return $image;
        }

        $scale = self::MAX_OUTPUT_DIMENSION / $longest;
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $resized = @imagescale($image, $targetWidth, $targetHeight, IMG_BICUBIC);

        if ($resized === false) {
            return $image;
        }

        imagedestroy($image);

        return $resized;
    }

    /**
     * Give paletted sources a true-colour canvas and keep the alpha channel
     * so transparent images stay transparent through the encode.
     */
    private static function prepareForEncoding(\GdImage $image): void
    {
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
    }
}
