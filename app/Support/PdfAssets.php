<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

final class PdfAssets
{
    /**
     * Logo sebagai data URI agar DomPDF tidak perlu mengakses file atau URL eksternal.
     * Mengembalikan null jika logo belum diunggah atau filenya hilang.
     */
    public static function logoDataUri(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($disk->get($path));
    }

    /**
     * Gambar yang dipotong (crop tengah) dan diubah ukurannya menjadi $width x $height piksel,
     * sebagai data URI JPEG. Rasio asli gambar tidak membuatnya gepeng.
     * Mengembalikan null jika file hilang atau bukan gambar yang valid.
     */
    public static function croppedImageDataUri(?string $path, int $width, int $height): ?string
    {
        if ($path === null || $path === '' || $width < 1 || $height < 1) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        try {
            $source = @imagecreatefromstring($disk->get($path));

            if ($source === false) {
                return null;
            }

            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $targetRatio = $width / $height;

            if ($sourceWidth / $sourceHeight > $targetRatio) {
                $cropHeight = $sourceHeight;
                $cropWidth = (int) round($sourceHeight * $targetRatio);
                $x = (int) (($sourceWidth - $cropWidth) / 2);
                $y = 0;
            } else {
                $cropWidth = $sourceWidth;
                $cropHeight = (int) round($sourceWidth / $targetRatio);
                $x = 0;
                $y = (int) (($sourceHeight - $cropHeight) / 2);
            }

            $canvas = imagecreatetruecolor($width, $height);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255)); // latar putih untuk PNG transparan
            imagecopyresampled($canvas, $source, 0, 0, $x, $y, $width, $height, $cropWidth, $cropHeight);

            ob_start();
            imagejpeg($canvas, null, 88);
            $jpeg = (string) ob_get_clean();

            imagedestroy($source);
            imagedestroy($canvas);

            return 'data:image/jpeg;base64,' . base64_encode($jpeg);
        } catch (Throwable) {
            return null;
        }
    }
}
