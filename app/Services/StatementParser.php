<?php

namespace App\Services;

use App\Exceptions\StatementParseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the statement-parser service (../statement_parser, FastAPI).
 *
 * The decrypted file goes to the parser over the internal network only, as
 * the request body; the PDF password (if any) only in a header. The parser
 * keeps nothing, and nothing from the file or the password is logged here.
 * Fast parses answer at once; scans continue as a job that is polled.
 */
class StatementParser
{
    public function enabled(): bool
    {
        return (string) config('navesta.statements.parser.url') !== '';
    }

    /**
     * @param  'card'|'loan'  $kind
     * @return array{status: 'done', result: array<string, mixed>}|array{status: 'processing', jobId: string}
     */
    public function submit(string $contents, string $mimeType, string $kind, ?string $password = null): array
    {
        $headers = [
            // Only the extension is used by the parser; the customer's file name is not sent
            'X-Filename' => str_contains($mimeType, 'pdf') ? 'statement.pdf' : 'statement.csv',
        ];
        if ($password !== null && $password !== '') {
            $headers['X-Statement-Password'] = $password;
        }

        return $this->handle(fn () => $this->client()
            ->withHeaders($headers)
            ->withBody($contents, $mimeType ?: 'application/octet-stream')
            ->post("/v1/statements/{$kind}"));
    }

    /**
     * @return array{status: 'done', result: array<string, mixed>}|array{status: 'processing', jobId: string}
     */
    public function poll(string $jobId): array
    {
        if (! preg_match('/^[A-Za-z0-9_-]{8,64}$/', $jobId)) {
            throw new StatementParseException('expired', 'That reading has expired. Please try again.', 422);
        }

        return $this->handle(fn () => $this->client()->get("/v1/jobs/{$jobId}"));
    }

    private function client()
    {
        return Http::baseUrl(rtrim((string) config('navesta.statements.parser.url'), '/'))
            ->acceptJson()
            // the parser answers inline within ~20 s, else 202; leave a margin
            ->timeout((int) config('navesta.statements.parser.timeout', 30));
    }

    /**
     * @param  callable(): Response  $send
     */
    private function handle(callable $send): array
    {
        if (! $this->enabled()) {
            throw new StatementParseException('unavailable', "Automatic reading isn't available right now. Please enter the figures yourself.", 503);
        }

        try {
            $response = $send();
        } catch (ConnectionException $e) {
            Log::warning('Statement parser unreachable', ['error' => $e::class]);
            throw new StatementParseException('unavailable', "We couldn't read the statement right now. Please enter the figures yourself.", 503);
        }

        if ($response->status() === 202) {
            $jobId = (string) $response->json('job_id', '');
            if ($jobId === '') {
                throw new StatementParseException('unavailable', "We couldn't read the statement right now. Please enter the figures yourself.", 503);
            }

            return ['status' => 'processing', 'jobId' => $jobId];
        }

        if ($response->successful()) {
            return ['status' => 'done', 'result' => (array) $response->json()];
        }

        $code = (string) $response->json('error.code', '');
        // Parser messages for these codes are written to be shown to customers
        $message = (string) $response->json('error.message', '');

        throw match (true) {
            $code === 'password_required' => new StatementParseException($code, 'This PDF is password protected. Enter its password to read it (it is used once and never saved).', 422),
            $code === 'wrong_password' => new StatementParseException($code, "That password didn't open the PDF. Please try again.", 422),
            $code === 'bad_file' => new StatementParseException($code, $message ?: "We couldn't read that file.", 422),
            $code === 'file_too_large' => new StatementParseException($code, $message ?: 'That file is too large.', 422),
            $response->status() === 404 => new StatementParseException('expired', 'That reading has expired. Please try again.', 422),
            in_array($code, ['too_many_jobs', 'busy'], true) => new StatementParseException('busy', "We're busy reading other statements. Please try again in a minute.", 503),
            default => $this->unexpected($response),
        };
    }

    private function unexpected(Response $response): StatementParseException
    {
        Log::warning('Statement parser error', ['status' => $response->status()]);

        return new StatementParseException('unavailable', "We couldn't read the statement right now. Please enter the figures yourself.", 503);
    }
}
