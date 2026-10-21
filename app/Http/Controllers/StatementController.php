<?php

namespace App\Http\Controllers;

use App\Http\Resources\StatementResource;
use App\Models\Statement;
use App\Services\AuditLogger;
use App\Services\StatementVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StatementController extends Controller
{
    public function __construct(private StatementVault $vault) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($request, $request->user()->statements()->latest(), StatementResource::class);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            // Bank and card statements: PDF or CSV exports only
            'file' => ['required', 'file', 'mimes:pdf,csv,txt', 'mimetypes:application/pdf,text/csv,text/plain,application/csv', 'max:'.config('navesta.statements.max_upload_kb')],
        ]);

        $statement = $this->vault->store($request->user(), $request->file('file'));

        return $this->ok(new StatementResource($statement->refresh()), 'Statement uploaded', 201);
    }

    /**
     * Direct upload initiation: provides presigned parameters
     * for direct S3/object-storage uploads.
     */
    public function presignedUrl(Request $request): JsonResponse
    {
        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mimeType' => ['required', 'string', 'in:application/pdf,text/csv,text/plain,application/octet-stream'],
            'sizeBytes' => ['required', 'integer', 'min:1', 'max:'.(config('navesta.statements.max_upload_kb') * 1024)],
        ]);

        $data = $this->vault->createPresignedUpload(
            $request->user(),
            $request->input('filename'),
            $request->input('mimeType'),
            (int) $request->input('sizeBytes'),
        );

        return $this->ok($data, 'Upload parameters generated');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return $this->ok(new StatementResource($this->findStatement($request, $id)));
    }

    public function download(Request $request, string $id): Response
    {
        $statement = $this->findStatement($request, $id);

        if (! $statement->isDownloadable()) {
            abort(409, "This file is not available (status: {$statement->status->getLabel()})");
        }

        app(AuditLogger::class)->record('statement.downloaded', $statement);

        return response()->streamDownload(
            function () use ($statement) {
                echo $this->vault->contents($statement);
            },
            $statement->original_name,
            [
                'Content-Type' => $statement->mime_type,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'no-store',
            ],
        );
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->vault->delete($this->findStatement($request, $id));

        return $this->ok(['id' => $id], 'Statement deleted');
    }

    private function findStatement(Request $request, string $id): Statement
    {
        return $request->user()->statements()->find($id) ?? abort(404, 'Statement not found');
    }
}
