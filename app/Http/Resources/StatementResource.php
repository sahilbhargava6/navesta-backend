<?php

namespace App\Http\Resources;

use App\Models\Statement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Statement */
class StatementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fileName' => $this->original_name,
            'mimeType' => $this->mime_type,
            'sizeBytes' => $this->size_bytes,
            'status' => $this->status,
            'downloadable' => $this->isDownloadable(),
            'scannedAt' => $this->scanned_at,
            'uploadedAt' => $this->created_at,
        ];
    }
}
