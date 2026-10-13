<?php

namespace Tests\Feature\Worker;

use App\Enums\AssignmentStatus;
use App\Enums\RunStatus;
use App\Enums\RunType;
use App\Models\Assignment;
use App\Models\Run;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkerApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.worker.token' => 'secret-token']);
        Storage::fake();
    }

    private function worker(): self
    {
        return $this->withToken('secret-token');
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(array $overrides = []): array
    {
        return array_merge([
            'cmid' => 123,
            'event_id' => 9,
            'name' => 'Lista 1',
            'course' => 'Algoritmos',
            'module' => 'assign',
            'due_at' => now()->addDay()->timestamp,
            'overdue' => false,
            'url' => 'https://presencial.ifrs.edu.br/mod/assign/view.php?id=123',
            'statement_md' => 'Faça X',
            'summary' => 'Resumo',
            'review_notes' => 'Conferir Y',
            'status' => 'draft_ready',
        ], $overrides);
    }

    private function upload(Run $run, array $meta, array $draft = [], array $attachments = [])
    {
        return $this->worker()->post(route('worker.assignment', $run), [
            'meta' => json_encode($meta),
            'draft' => json_encode($draft),
            'attachments' => $attachments,
        ], ['Accept' => 'application/json']);
    }

    public function test_requests_without_a_valid_token_are_rejected(): void
    {
        $this->postJson(route('worker.claim'))->assertUnauthorized();
        $this->withToken('wrong')->postJson(route('worker.claim'))->assertUnauthorized();
    }

    public function test_claim_returns_no_content_when_there_is_nothing_to_do(): void
    {
        $this->worker()->postJson(route('worker.claim'))->assertNoContent();

        $this->assertNotNull(Setting::current()->worker_seen_at);
    }

    public function test_claim_hands_out_the_oldest_pending_run_once(): void
    {
        Setting::current()->update(['moodle_username' => 'aluno', 'moodle_password' => 'senha', 'anthropic_api_key' => 'sk-test']);
        $first = Run::queue(RunType::Manual);
        Run::queue(RunType::Scheduled);

        $this->worker()->postJson(route('worker.claim'))
            ->assertOk()
            ->assertJsonPath('run.id', $first->id)
            ->assertJsonPath('moodle.username', 'aluno')
            ->assertJsonPath('moodle.password', 'senha')
            ->assertJsonPath('anthropic_api_key', 'sk-test')
            ->assertJsonPath('target', null);

        $this->assertSame(RunStatus::Running, $first->fresh()->status);
        $this->worker()->postJson(route('worker.claim'))->assertOk()->assertJsonPath('run.id', $first->id + 1);
        $this->worker()->postJson(route('worker.claim'))->assertNoContent();
    }

    public function test_redo_runs_include_the_target_assignment(): void
    {
        $run = Run::query()->create(['type' => RunType::Manual, 'status' => RunStatus::Running, 'model' => 'claude-opus-5-5', 'effort' => 'high']);
        $assignment = Assignment::find($this->upload($run, $this->meta())->json('id'));

        $redo = Run::queue(RunType::Redo, assignment: $assignment);

        $this->worker()->postJson(route('worker.claim'))
            ->assertJsonPath('run.id', $redo->id)
            ->assertJsonPath('force', true)
            ->assertJsonPath('target.cmid', 123)
            ->assertJsonPath('done_cmids', [123]);
    }

    public function test_upload_stores_the_assignment_with_draft_and_attachments(): void
    {
        $run = Run::queue(RunType::Manual);
        $run->update(['status' => RunStatus::Running]);

        $this->upload($run, $this->meta(), [
            ['path' => 'resposta.md', 'content' => '# Resposta'],
            ['path' => './src/main.py', 'content' => 'print(1)'],
        ], [UploadedFile::fake()->create('lista.pdf', 10, 'application/pdf')])->assertCreated();

        $assignment = Assignment::query()->where('cmid', 123)->firstOrFail();
        $this->assertSame(AssignmentStatus::DraftReady, $assignment->status);
        $this->assertSame($run->model, $assignment->model);
        $this->assertSame(['resposta.md', 'src/main.py'], $assignment->draftFiles->pluck('path')->all());
        $this->assertSame('lista.pdf', $assignment->attachments->first()->path);
        Storage::assertExists($assignment->attachments->first()->disk_path);
    }

    public function test_upload_keeps_a_status_set_by_the_user(): void
    {
        $run = Run::queue(RunType::Manual);
        $run->update(['status' => RunStatus::Running]);
        $this->upload($run, $this->meta())->assertCreated();
        Assignment::query()->where('cmid', 123)->update(['status' => AssignmentStatus::Submitted]);

        $this->upload($run, $this->meta(['review_notes' => 'Novas notas']), [['path' => 'v2.md', 'content' => 'v2']])->assertCreated();

        $assignment = Assignment::query()->where('cmid', 123)->firstOrFail();
        $this->assertSame(AssignmentStatus::Submitted, $assignment->status);
        $this->assertSame('Novas notas', $assignment->review_notes);
        $this->assertSame(['v2.md'], $assignment->draftFiles->pluck('path')->all());
    }

    public function test_upload_rejects_unsafe_draft_paths(): void
    {
        $run = Run::queue(RunType::Manual);
        $run->update(['status' => RunStatus::Running]);

        foreach (['../../.env', '/etc/passwd', 'a/../../b', 'C:\\x.txt'] as $path) {
            $this->upload($run, $this->meta(), [['path' => $path, 'content' => 'x']])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('draft.0.path');
        }
        $this->assertSame(0, Assignment::query()->count());
    }

    public function test_upload_requires_a_running_run(): void
    {
        $run = Run::queue(RunType::Manual);

        $this->upload($run, $this->meta())->assertConflict();
    }

    public function test_log_and_finish(): void
    {
        $run = Run::queue(RunType::Manual);
        $run->update(['status' => RunStatus::Running]);

        $this->worker()->postJson(route('worker.log', $run), ['lines' => ['a', 'b']])->assertNoContent();
        $this->worker()->postJson(route('worker.log', $run), ['lines' => ['c']])->assertNoContent();
        $this->worker()->postJson(route('worker.finish', $run), ['status' => 'failed', 'error' => 'boom'])->assertNoContent();

        $run->refresh();
        $this->assertSame("a\nb\nc", $run->log);
        $this->assertSame(RunStatus::Failed, $run->status);
        $this->assertSame('boom', $run->error);
        $this->assertNotNull($run->finished_at);
    }
}
