<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImageUploader
{
    public const DISK = 'public';

    /**
     * Aturan validasi gambar. Format SVG sengaja tidak diizinkan (bisa memuat script).
     *
     * @return array<int, string>
     */
    public static function rules(int $maxKilobytes = 2048): array
    {
        return [
            'nullable',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:' . $maxKilobytes,
            'dimensions:max_width=4000,max_height=4000',
        ];
    }

    public function store(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, self::DISK);
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
