<?php

namespace App\Services;

use App\Jobs\ScanStatement;
use App\Models\Statement;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores customer statements encrypted with the app key (AES-256-CBC + MAC)
 * before they reach the disk, so the storage provider only ever holds
 * ciphertext. Every upload is queued for a virus scan before it can be
 * downloaded.
 */
class StatementVault
{
    public function store(User $user, UploadedFile $file): Statement
    {
        $contents = $file->get();
        $disk = config('navesta.statements.disk');
        $path = "{$user->id}/".Str::uuid().'.enc';

        Storage::disk($disk)->put($path, Crypt::encryptString($contents));

        $statement = $user->statements()->create([
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 250, ''),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => strlen($contents),
            'sha256' => hash('sha256', $contents),
        ]);

        ScanStatement::dispatch($statement);

        return $statement;
    }

    /**
     * High-scale direct-to-storage upload: generates presigned upload parameters
     * so customer statements can be uploaded directly to cloud object storage (S3/R2),
     * completely bypassing API server bandwidth and thread allocation.
     */
    public function createPresignedUpload(User $user, string $filename, string $mimeType, int $sizeBytes): array
    {
        $disk = config('navesta.statements.disk');
        $storage = Storage::disk($disk);
        $path = "{$user->id}/".Str::uuid().'.enc';

        if (method_exists($storage, 'temporaryUploadUrl')) {
            $uploadUrl = $storage->temporaryUploadUrl($path, now()->addMinutes(15));

            return [
                'direct_upload' => true,
                'upload_url' => $uploadUrl,
                'path' => $path,
                'disk' => $disk,
            ];
        }

        return [
            'direct_upload' => false,
            'fallback_url' => url('/api/v1/statements'),
            'path' => $path,
            'disk' => $disk,
        ];
    }

    public function contents(Statement $statement): string
    {
        return Crypt::decryptString(Storage::disk($statement->disk)->get($statement->path));
    }

    public function delete(Statement $statement): void
    {
        if ($statement->path) {
            Storage::disk($statement->disk)->delete($statement->path);
        }

        $statement->delete();
    }
}
