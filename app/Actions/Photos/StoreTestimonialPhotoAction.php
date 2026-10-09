<?php

declare(strict_types=1);

namespace App\Actions\Photos;

use App\Exceptions\UnreadablePhotoException;
use App\Http\Controllers\Public\TestimonialPhotoController;
use App\Models\Space;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Profile-photo upload (PRD §15, §31 "protect uploaded profile images").
 *
 * Preferred path: decode with GD and re-encode a fresh image, which
 *   - drops EXIF/metadata (GPS location, camera serials),
 *   - neutralises polyglot files (an "image" that is also HTML/JS),
 *   - caps size at 512px, which is all an avatar needs.
 *
 * Fallback, when this PHP's GD lacks a codec (e.g. no JPEG support):
 * the bytes are kept only after `getimagesize()` confirms a real
 * JPEG/PNG/WebP, and a warning is logged. Either way files are
 * served with their image Content-Type, `nosniff` and a deny-all CSP
 * by {@see TestimonialPhotoController},
 * so they can never execute as a page or script.
 *
 * Files go to the private `local` disk under a random name.
 */
class StoreTestimonialPhotoAction
{
    public const DIRECTORY = 'testimonial-photos';

    /** @var array<string, string> MIME => extension for accepted inputs */
    private const ACCEPTED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private const MAX_EDGE = 512;

    private const JPEG_QUALITY = 85;

    public function store(Space $space, UploadedFile $file): string
    {
        $bytes = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($bytes);
        $mime = is_array($info) ? $info['mime'] : null;

        if ($mime === null || ! isset(self::ACCEPTED[$mime])) {
            throw new UnreadablePhotoException('Not a JPEG, PNG or WebP image.');
        }

        $source = $this->canDecode($mime) ? @imagecreatefromstring($bytes) : false;

        if ($source instanceof GdImage) {
            [$output, $extension] = $this->reencode($source);
        } else {
            Log::warning('GD cannot decode this photo format; storing the validated original.', ['mime' => $mime]);
            $output = $bytes;
            $extension = self::ACCEPTED[$mime];
        }

        $path = self::DIRECTORY.'/'.$space->id.'/'.Str::uuid()->toString().'.'.$extension;
        Storage::disk('local')->put($path, $output);

        return $path;
    }

    /**
     * True only for paths this action produced — never trust a stored
     * value to be a safe filesystem path otherwise.
     */
    public static function isStoredPath(?string $path): bool
    {
        return is_string($path)
            && preg_match('#^'.self::DIRECTORY.'/\d+/[0-9a-f-]{36}\.(jpg|png|webp)$#', $path) === 1;
    }

    public static function contentType(string $path): string
    {
        return match (pathinfo($path, PATHINFO_EXTENSION)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }

    private function canDecode(string $mime): bool
    {
        return match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg'),
            'image/png' => function_exists('imagecreatefrompng'),
            'image/webp' => function_exists('imagecreatefromwebp'),
            default => false,
        };
    }

    /**
     * @return array{0: string, 1: string} bytes and file extension
     */
    private function reencode(GdImage $source): array
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        // Flatten transparency onto white so JPEG and PNG output match.
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        if (function_exists('imagejpeg')) {
            imagejpeg($canvas, null, self::JPEG_QUALITY);
            $extension = 'jpg';
        } else {
            imagepng($canvas, null, 9);
            $extension = 'png';
        }

        return [(string) ob_get_clean(), $extension];
    }
}
