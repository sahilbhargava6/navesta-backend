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
            // Figures read by the statement parser, if it has been run
            'parsed' => $this->parsed_at ? [
                'kind' => $this->parsed_kind,
                'fields' => (object) ($this->parsed_fields ?? []),
                'needsReview' => (bool) $this->parse_needs_review,
                'parsedAt' => $this->parsed_at,
            ] : null,
            'uploadedAt' => $this->created_at,
        ];
    }
}
