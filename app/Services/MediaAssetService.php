<?php

namespace App\Services;

use App\Models\MediaAsset;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaAssetService
{
    public function storeUploadedFile(
        UploadedFile $file,
        string $category,
        ?Model $owner,
        ?User $uploadedBy,
        string $pathPrefix,
        ?string $disk = null,
    ): MediaAsset {
        $disk ??= (string) config('filesystems.default', 'local');

        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $filename = sprintf('%s_%s.%s', now()->format('Ymd'), (string) Str::uuid(), $extension);
        $objectKey = trim($pathPrefix, '/').'/'.$filename;

        $stream = fopen($file->getRealPath(), 'rb');
        Storage::disk($disk)->put($objectKey, $stream, [
            'visibility' => 'private',
            'ContentType' => $file->getMimeType(),
        ]);

        if (is_resource($stream)) {
            fclose($stream);
        }

        [$width, $height] = $this->extractImageSize($file->getRealPath());

        return MediaAsset::query()->create([
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
            'category' => $category,
            'disk' => $disk,
            'bucket' => $this->bucketForDisk($disk),
            'object_key' => $objectKey,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize() ?: 0,
            'checksum' => hash_file('sha256', $file->getRealPath()),
            'width' => $width,
            'height' => $height,
            'uploaded_by' => $uploadedBy?->id,
        ]);
    }

    public function storeContent(
        string $content,
        string $category,
        ?Model $owner,
        ?User $uploadedBy,
        string $pathPrefix,
        string $extension,
        string $mimeType,
        ?string $disk = null,
    ): MediaAsset {
        $disk ??= (string) config('filesystems.default', 'local');

        $filename = sprintf('%s_%s.%s', now()->format('Ymd'), (string) Str::uuid(), $extension);
        $objectKey = trim($pathPrefix, '/').'/'.$filename;

        Storage::disk($disk)->put($objectKey, $content, [
            'visibility' => 'private',
            'ContentType' => $mimeType,
        ]);

        return MediaAsset::query()->create([
            'owner_type' => $owner?->getMorphClass(),
            'owner_id' => $owner?->getKey(),
            'category' => $category,
            'disk' => $disk,
            'bucket' => $this->bucketForDisk($disk),
            'object_key' => $objectKey,
            'original_name' => basename($objectKey),
            'mime_type' => $mimeType,
            'size_bytes' => strlen($content),
            'checksum' => hash('sha256', $content),
            'uploaded_by' => $uploadedBy?->id,
        ]);
    }

    public function temporaryUrl(MediaAsset $mediaAsset, CarbonInterface $expiration): string
    {
        return Storage::disk($mediaAsset->disk)->temporaryUrl($mediaAsset->object_key, $expiration);
    }

    protected function bucketForDisk(string $disk): string
    {
        $bucket = (string) config("filesystems.disks.{$disk}.bucket", '');

        if ($bucket !== '') {
            return $bucket;
        }

        $root = (string) config("filesystems.disks.{$disk}.root", $disk);

        return basename($root) ?: $disk;
    }

    protected function extractImageSize(string $path): array
    {
        $size = @getimagesize($path);

        if (! is_array($size)) {
            return [null, null];
        }

        return [$size[0] ?? null, $size[1] ?? null];
    }
}
