<?php

namespace Tests\Feature;

use App\Enums\StatementStatus;
use App\Models\AuditLog;
use App\Models\Statement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Reading the key figures from an uploaded statement with the statement-parser
 * service (faked here; the service itself has its own tests).
 */
class StatementParseTest extends TestCase
{
    use RefreshDatabase;

    private const PDF = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

    private const PARSER = 'http://statement-parser:8000';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('statements');
        config(['navesta.statements.parser.url' => self::PARSER]);
    }

    private function cardResult(bool $needsReview = false): array
    {
        return [
            'parser_version' => '2026.10.4', 'statement_kind' => 'card', 'file_kind' => 'pdf', 'ocr' => false,
            'fields' => [
                'outstanding' => ['value' => 84500, 'source' => 'summary_table', 'confidence' => 'high'],
                'min_due' => ['value' => 4225, 'source' => 'summary_table', 'confidence' => 'high'],
                'credit_limit' => ['value' => 200000, 'source' => 'label', 'confidence' => 'high'],
                'apr' => ['value' => 42.0, 'source' => 'heuristic', 'confidence' => 'low'],
                'due_date' => ['value' => '2026-12-05', 'source' => 'label', 'confidence' => 'high'],
                'payments_received' => ['value' => null, 'source' => null, 'confidence' => null],
            ],
            'transactions' => [['date' => '2026-10-02', 'description' => 'AMAZON', 'amount' => -1845.5]],
            'transactions_method' => 'inline', 'transactions_confidence' => 'medium', 'ledger' => null,
            'needs_review' => $needsReview, 'warnings' => $needsReview ? ['field_rejected:apr'] : [],
        ];
    }

    /** @return array{0: array, 1: string} headers and statement id */
    private function uploaded(): array
    {
        $headers = $this->authHeaders($this->createUser());
        $id = $this->post('/api/v1/statements', ['file' => UploadedFile::fake()->createWithContent('my-hdfc-card.pdf', self::PDF)], $headers + ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');

        return [$headers, $id];
    }

    public function test_card_figures_are_read_saved_encrypted_and_returned(): void
    {
        Http::fake([self::PARSER.'/*' => Http::response($this->cardResult(needsReview: true))]);
        [$headers, $id] = $this->uploaded();

        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'card'], $headers)
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.fields.outstanding.value', 84500)
            ->assertJsonPath('data.fields.minDue.value', 4225)
            ->assertJsonPath('data.fields.creditLimit.value', 200000)
            ->assertJsonPath('data.fields.aprPercent.confidence', 'low')
            ->assertJsonPath('data.fields.dueDate.value', '2026-12-05')
            ->assertJsonMissingPath('data.fields.paymentsReceived')
            ->assertJsonMissingPath('data.transactions')
            ->assertJsonPath('data.needsReview', true);

        // The decrypted file went to the parser as the body, without the customer's file name
        Http::assertSent(fn (Request $r) => $r->url() === self::PARSER.'/v1/statements/card'
            && $r->body() === self::PDF
            && $r->header('X-Filename')[0] === 'statement.pdf'
            && ! $r->hasHeader('X-Statement-Password'));

        // Figures are encrypted at rest; transactions are not kept
        $raw = DB::table('statements')->where('id', $id)->value('parsed_fields');
        $this->assertStringNotContainsString('84500', $raw);
        $this->assertStringNotContainsString('AMAZON', json_encode(Statement::find($id)->parsed_fields));

        // Shown with the statement afterwards, and audited without any figures
        $this->getJson("/api/v1/statements/{$id}", $headers)->assertJsonPath('data.parsed.fields.minDue.value', 4225);
        $audit = AuditLog::where('action', 'statement.parsed')->firstOrFail();
        $this->assertStringNotContainsString('84500', json_encode($audit->metadata));
    }

    public function test_password_protected_pdfs_ask_for_the_password_which_is_passed_through_once(): void
    {
        Http::fake([self::PARSER.'/*' => Http::sequence()
            ->push(['error' => ['code' => 'password_required', 'message' => 'This PDF is password protected']], 422)
            ->push($this->cardResult())]);
        [$headers, $id] = $this->uploaded();

        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'card'], $headers)
            ->assertStatus(422)->assertJsonPath('code', 'password_required');

        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'card', 'password' => 'ASHA0101'], $headers)->assertOk();

        Http::assertSent(fn (Request $r) => ($r->header('X-Statement-Password')[0] ?? null) === 'ASHA0101');
        $this->assertStringNotContainsString('ASHA0101', json_encode(AuditLog::all()->toArray()));
        $this->assertStringNotContainsString('ASHA0101', json_encode(Statement::find($id)->toArray()));
    }

    public function test_slow_scans_are_polled_until_done(): void
    {
        Http::fake([
            self::PARSER.'/v1/statements/*' => Http::response(['status' => 'processing', 'job_id' => 'job_abcdefgh123', 'status_url' => '/v1/jobs/job_abcdefgh123'], 202),
            self::PARSER.'/v1/jobs/*' => Http::sequence()
                ->push(['status' => 'processing', 'job_id' => 'job_abcdefgh123'], 202)
                ->push($this->cardResult()),
        ]);
        [$headers, $id] = $this->uploaded();

        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'card'], $headers)->assertStatus(202)->assertJsonPath('data.status', 'processing');
        $this->getJson("/api/v1/statements/{$id}/parse", $headers)->assertStatus(202);
        $this->getJson("/api/v1/statements/{$id}/parse", $headers)->assertOk()->assertJsonPath('data.fields.outstanding.value', 84500);

        // Afterwards the saved figures are returned without asking the parser again
        $this->getJson("/api/v1/statements/{$id}/parse", $headers)->assertOk()->assertJsonPath('data.fields.minDue.value', 4225);
        Http::assertSentCount(3);
    }

    public function test_loan_statements_map_their_own_fields(): void
    {
        Http::fake([self::PARSER.'/*' => Http::response([
            'parser_version' => '2026.10.4', 'statement_kind' => 'loan', 'file_kind' => 'pdf', 'ocr' => true,
            'fields' => [
                'outstanding' => ['value' => 312000, 'source' => 'label', 'confidence' => 'medium'],
                'rate' => ['value' => 13.5, 'source' => 'label', 'confidence' => 'medium'],
                'emi' => ['value' => 11250, 'source' => 'label', 'confidence' => 'medium'],
                'months_left' => ['value' => 31, 'source' => 'label', 'confidence' => 'medium'],
                'next_emi' => ['value' => '2026-12-07', 'source' => 'label', 'confidence' => 'medium'],
            ],
            'transactions' => null, 'transactions_method' => null, 'transactions_confidence' => null, 'ledger' => null,
            'needs_review' => true, 'warnings' => ['ocr_used'],
        ])]);
        [$headers, $id] = $this->uploaded();

        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'loan'], $headers)
            ->assertOk()
            ->assertJsonPath('data.fields.ratePercent.value', 13.5)
            ->assertJsonPath('data.fields.monthsLeft.value', 31)
            ->assertJsonPath('data.fields.nextEmiDate.value', '2026-12-07')
            ->assertJsonPath('data.warnings.0', 'ocr_used');
    }

    public function test_unreadable_files_and_outages_fall_back_to_manual_entry(): void
    {
        Http::fake([self::PARSER.'/*' => Http::sequence()
            ->push(['error' => ['code' => 'bad_file', 'message' => "We couldn't read that file"]], 422)
            ->push(['error' => ['code' => 'busy', 'message' => 'busy']], 503)
            ->pushStatus(500)]);
        [$headers, $id] = $this->uploaded();

        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'card'], $headers)->assertStatus(422)->assertJsonPath('code', 'bad_file');
        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'card'], $headers)->assertStatus(503)->assertJsonPath('code', 'busy');
        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'card'], $headers)->assertStatus(503)->assertJsonPath('code', 'unavailable');

        config(['navesta.statements.parser.url' => null]);
        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'card'], $headers)->assertStatus(503)->assertJsonPath('code', 'unavailable');
    }

    public function test_files_still_being_scanned_or_quarantined_are_not_read(): void
    {
        Http::fake();
        [$headers, $id] = $this->uploaded();

        Statement::whereKey($id)->update(['status' => StatementStatus::PendingScan]);
        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'card'], $headers)->assertStatus(409);

        Statement::whereKey($id)->update(['status' => StatementStatus::Infected, 'path' => null]);
        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'card'], $headers)->assertStatus(409);

        Http::assertNothingSent();
    }

    public function test_customers_can_only_read_their_own_statements(): void
    {
        Http::fake();
        [, $id] = $this->uploaded();
        $this->app['auth']->forgetGuards(); // the test client keeps the first user otherwise

        $this->postJson("/api/v1/statements/{$id}/parse", ['kind' => 'card'], $this->authHeaders($this->createUser()))->assertNotFound();
        Http::assertNothingSent();
    }
}
