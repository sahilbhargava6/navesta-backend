<?php

namespace Tests\Feature;

use App\Contracts\VirusScanner;
use App\Enums\StatementStatus;
use App\Jobs\ScanStatement;
use App\Models\Statement;
use App\Services\Scanning\ClamAvScanner;
use App\Support\ScanResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class StatementTest extends TestCase
{
    use RefreshDatabase;

    private const CSV = "date,description,amount\n2026-10-01,Grocery,1250.00\n";

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('statements');
    }

    private function upload(array $headers, ?UploadedFile $file = null)
    {
        return $this->post('/api/v1/statements', [
            'file' => $file ?? UploadedFile::fake()->createWithContent('hdfc-oct.csv', self::CSV),
        ], $headers + ['Accept' => 'application/json']);
    }

    public function test_upload_is_stored_encrypted_and_downloads_unchanged(): void
    {
        $user = $this->createUser();
        $headers = $this->authHeaders($user);

        $id = $this->upload($headers)
            ->assertCreated()
            ->assertJsonPath('data.fileName', 'hdfc-oct.csv')
            ->assertJsonPath('data.status', 'unscanned') // STATEMENT_SCANNER=none in tests
            ->assertJsonPath('data.downloadable', true)
            ->json('data.id');

        $statement = Statement::find($id);
        $stored = Storage::disk('statements')->get($statement->path);
        $this->assertStringNotContainsString('Grocery', $stored);
        $this->assertSame(hash('sha256', self::CSV), $statement->sha256);

        $download = $this->get("/api/v1/statements/{$id}/download", $headers);
        $download->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(self::CSV, $download->streamedContent());
    }

    public function test_infected_upload_is_deleted_and_blocked(): void
    {
        $this->app->instance(VirusScanner::class, new class implements VirusScanner
        {
            public function scan(string $contents): ScanResult
            {
                return ScanResult::infected('Eicar-Test-Signature');
            }
        });

        $user = $this->createUser();
        $headers = $this->authHeaders($user);

        $id = $this->upload($headers)
            ->assertCreated()
            ->assertJsonPath('data.status', 'infected')
            ->assertJsonPath('data.downloadable', false)
            ->json('data.id');

        $this->assertNull(Statement::find($id)->path);
        $this->assertSame([], Storage::disk('statements')->allFiles());
        $this->get("/api/v1/statements/{$id}/download", $headers)->assertStatus(409);
    }

    public function test_upload_waits_for_the_scan_before_it_can_be_downloaded(): void
    {
        Queue::fake();
        $user = $this->createUser();
        $headers = $this->authHeaders($user);

        $id = $this->upload($headers)->assertJsonPath('data.status', 'pending_scan')->json('data.id');

        Queue::assertPushed(ScanStatement::class);
        $this->get("/api/v1/statements/{$id}/download", $headers)->assertStatus(409);
    }

    public function test_scan_failure_after_retries_locks_the_file(): void
    {
        $user = $this->createUser();
        $statement = $user->statements()->create(['original_name' => 'x.pdf', 'disk' => 'statements', 'path' => 'x.enc', 'mime_type' => 'application/pdf', 'size_bytes' => 1, 'sha256' => str_repeat('0', 64)]);

        (new ScanStatement($statement))->failed(new RuntimeException('clamd down'));

        $this->assertSame(StatementStatus::ScanFailed, $statement->fresh()->status);
        $this->assertFalse($statement->fresh()->isDownloadable());
    }

    public function test_only_pdf_and_csv_are_accepted(): void
    {
        $headers = $this->authHeaders($this->createUser());

        $this->upload($headers, UploadedFile::fake()->createWithContent('run.exe', 'MZ'))->assertStatus(400);
        $this->upload($headers, UploadedFile::fake()->create('big.pdf', 20000, 'application/pdf'))->assertStatus(400);
    }

    public function test_statements_are_private_to_their_owner(): void
    {
        $owner = $this->createUser();
        $id = $this->upload($this->authHeaders($owner))->json('data.id');

        $this->app['auth']->forgetGuards();
        $intruder = $this->authHeaders($this->createUser());

        $this->get("/api/v1/statements/{$id}/download", $intruder + ['Accept' => 'application/json'])->assertNotFound();
        $this->deleteJson("/api/v1/statements/{$id}", [], $intruder)->assertNotFound();
    }

    public function test_delete_removes_the_file(): void
    {
        $headers = $this->authHeaders($this->createUser());
        $id = $this->upload($headers)->json('data.id');

        $this->deleteJson("/api/v1/statements/{$id}", [], $headers)->assertOk();

        $this->assertSame([], Storage::disk('statements')->allFiles());
        $this->assertDatabaseMissing('statements', ['id' => $id]);
    }

    public function test_clamav_client_speaks_the_instream_protocol(): void
    {
        [$client, $server] = stream_socket_pair(STREAM_PF_INET, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($server, "stream: Eicar-Test-Signature FOUND\0");

        $result = (new ClamAvScanner('unused', 0, 5, fn () => $client))->scan('hello');

        $this->assertFalse($result->clean);
        $this->assertSame('Eicar-Test-Signature', $result->signature);

        $sent = fread($server, 1024);
        $this->assertSame("zINSTREAM\0".pack('N', 5).'hello'.pack('N', 0), $sent);
    }

    public function test_clamav_client_reports_clean_files(): void
    {
        [$client, $server] = stream_socket_pair(STREAM_PF_INET, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        fwrite($server, "stream: OK\0");

        $this->assertTrue((new ClamAvScanner('unused', 0, 5, fn () => $client))->scan('clean')->clean);
    }
}
