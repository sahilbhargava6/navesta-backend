<?php

namespace App\Http\Controllers;

use App\Enums\StatementStatus;
use App\Exceptions\StatementParseException;
use App\Http\Resources\StatementResource;
use App\Models\Statement;
use App\Services\AuditLogger;
use App\Services\StatementParser;
use App\Services\StatementVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

    /**
     * Read the key figures from an uploaded statement with the statement-parser
     * service. 200 = figures (show them for the customer to confirm when
     * needsReview is true); 202 = still reading (poll GET .../parse);
     * 422 code password_required / wrong_password = ask for the PDF password
     * and call again. The password is passed through once and never stored.
     */
    public function parse(Request $request, string $id, StatementParser $parser): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:card,loan'],
            'password' => ['nullable', 'string', 'max:128'],
        ]);
        $statement = $this->findStatement($request, $id);
        $this->ensureReadable($statement);

        try {
            $outcome = $parser->submit($this->vault->contents($statement), $statement->mime_type, $data['kind'], $data['password'] ?? null);
        } catch (StatementParseException $e) {
            return $this->parseFailed($e);
        }

        if ($outcome['status'] === 'processing') {
            Cache::put($this->parseJobKey($statement), ['job' => $outcome['jobId'], 'kind' => $data['kind']], now()->addMinutes(10));

            return $this->ok(['status' => 'processing'], 'Still reading the statement', 202);
        }

        return $this->ok($this->saveParse($statement, $data['kind'], $outcome['result']), 'Statement read');
    }

    /**
     * Progress of a slow read (usually a scanned PDF), or the figures already saved.
     */
    public function parseStatus(Request $request, string $id, StatementParser $parser): JsonResponse
    {
        $statement = $this->findStatement($request, $id);
        $pending = Cache::get($this->parseJobKey($statement));

        if (! is_array($pending)) {
            return $statement->parsed_at
                ? $this->ok($this->parsedPayload($statement))
                : $this->parseFailed(new StatementParseException('expired', 'That reading has expired. Please try again.', 422));
        }

        try {
            $outcome = $parser->poll((string) $pending['job']);
        } catch (StatementParseException $e) {
            Cache::forget($this->parseJobKey($statement));

            return $this->parseFailed($e);
        }

        if ($outcome['status'] === 'processing') {
            return $this->ok(['status' => 'processing'], 'Still reading the statement', 202);
        }

        Cache::forget($this->parseJobKey($statement));

        return $this->ok($this->saveParse($statement, (string) $pending['kind'], $outcome['result']), 'Statement read');
    }

    private function ensureReadable(Statement $statement): void
    {
        if ($statement->status === StatementStatus::PendingScan) {
            abort(409, "We're still checking this file for viruses. Please try again in a few seconds.");
        }
        if (! $statement->isDownloadable()) {
            abort(409, "This file can't be read (status: {$statement->status->getLabel()})");
        }
    }

    /**
     * Keeps only the key figures (value + confidence) for the statement kind;
     * transactions and the parser's working details are dropped.
     *
     * @param  array<string, mixed>  $result
     */
    private function saveParse(Statement $statement, string $kind, array $result): array
    {
        $allowed = self::PARSED_FIELDS[$kind];
        $fields = [];
        foreach ((array) ($result['fields'] ?? []) as $name => $field) {
            if (isset($allowed[$name]) && is_array($field) && ($field['value'] ?? null) !== null) {
                $fields[$allowed[$name]] = ['value' => $field['value'], 'confidence' => $field['confidence'] ?? null];
            }
        }

        $statement->update([
            'parsed_kind' => $kind,
            'parsed_fields' => $fields,
            'parse_needs_review' => (bool) ($result['needs_review'] ?? true),
            'parser_version' => substr((string) ($result['parser_version'] ?? ''), 0, 20) ?: null,
            'parsed_at' => now(),
        ]);

        app(AuditLogger::class)->record('statement.parsed', $statement, [
            'kind' => $kind,
            'fields_found' => count($fields),
            'needs_review' => $statement->parse_needs_review,
            'ocr' => (bool) ($result['ocr'] ?? false),
        ]);

        return $this->parsedPayload($statement, (array) ($result['warnings'] ?? []));
    }

    private function parsedPayload(Statement $statement, array $warnings = []): array
    {
        return [
            'status' => 'done',
            'statementId' => $statement->id,
            'kind' => $statement->parsed_kind,
            'fields' => (object) ($statement->parsed_fields ?? []),
            'needsReview' => (bool) $statement->parse_needs_review,
            'warnings' => array_values(array_filter($warnings, 'is_string')),
            'parsedAt' => $statement->parsed_at,
        ];
    }

    private function parseFailed(StatementParseException $e): JsonResponse
    {
        return response()->json(['success' => false, 'error' => $e->getMessage(), 'code' => $e->reason], $e->status);
    }

    private function parseJobKey(Statement $statement): string
    {
        return "statement-parse:{$statement->id}";
    }

    /** Parser field names → API names, per statement kind. */
    private const PARSED_FIELDS = [
        'card' => [
            'outstanding' => 'outstanding',
            'min_due' => 'minDue',
            'credit_limit' => 'creditLimit',
            'apr' => 'aprPercent',
            'due_date' => 'dueDate',
            'payments_received' => 'paymentsReceived',
        ],
        'loan' => [
            'outstanding' => 'outstanding',
            'rate' => 'ratePercent',
            'emi' => 'emi',
            'months_left' => 'monthsLeft',
            'next_emi' => 'nextEmiDate',
        ],
    ];

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
