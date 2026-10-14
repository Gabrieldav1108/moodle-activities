<?php

namespace Tests\Feature;

use App\Enums\AssignmentStatus;
use App\Enums\RunType;
use App\Models\Assignment;
use App\Models\AssignmentFile;
use App\Models\Run;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PagesTest extends TestCase
{
    use RefreshDatabase;

    private function assignment(): Assignment
    {
        $assignment = Assignment::query()->create([
            'cmid' => 55, 'name' => 'Relatório', 'course' => 'Redes', 'module' => 'assign',
            'url' => 'https://example.com', 'status' => AssignmentStatus::DraftReady,
            'review_notes' => "## Conferir\n- item <script>x</script>",
        ]);
        $assignment->files()->create(['kind' => AssignmentFile::KIND_DRAFT, 'path' => 'resposta.md', 'content' => 'v1']);

        return $assignment;
    }

    public function test_pages_render_for_authenticated_users(): void
    {
        $this->actingAs(User::factory()->create());
        $assignment = $this->assignment();
        $run = Run::queue(RunType::Manual);

        $this->get(route('dashboard'))->assertOk()->assertSee('Relatório');
        $this->get(route('automation.edit'))->assertOk();
        $this->get(route('runs.index'))->assertOk();
        $this->get(route('runs.show', $run))->assertOk();
        $this->get(route('assignments.index'))->assertOk()->assertSee('Relatório');
        $this->get(route('assignments.show', $assignment))->assertOk()
            ->assertSee('v1')
            ->assertDontSee('<script>x</script>', false);
        $this->get(route('assignments.zip', $assignment))->assertOk();
    }

    public function test_guests_cannot_see_assignments(): void
    {
        $this->get(route('assignments.index'))->assertRedirect(route('login'));
    }

    public function test_saving_automation_settings_keeps_blank_secrets(): void
    {
        $this->actingAs(User::factory()->create());
        Setting::current()->update(['moodle_password' => 'old-secret']);

        Livewire::test('pages::automation')
            ->set('enabled', true)
            ->set('run_times', ['19:00', '07:30'])
            ->set('weekdays', ['1', '3'])
            ->set('engine', 'api')
            ->set('model', 'claude-sonnet-5-5')
            ->set('effort', 'medium')
            ->set('anthropic_api_key', 'sk-new')
            ->call('save')
            ->assertHasNoErrors();

        $settings = Setting::current();
        $this->assertTrue($settings->enabled);
        $this->assertSame(['07:30', '19:00'], $settings->run_times);
        $this->assertSame([1, 3], $settings->weekdays);
        $this->assertSame('claude-sonnet-5-5', $settings->model->value);
        $this->assertSame('api', $settings->engine->value);
        $this->assertSame('old-secret', $settings->moodle_password);
        $this->assertSame('sk-new', $settings->anthropic_api_key);
    }

    public function test_automation_rejects_invalid_times(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::automation')
            ->set('run_times', ['25:00'])
            ->call('save')
            ->assertHasErrors('run_times.0');
    }

    public function test_editing_a_draft_and_changing_status(): void
    {
        $this->actingAs(User::factory()->create());
        $assignment = $this->assignment();

        Livewire::test('pages::assignments.show', ['assignment' => $assignment])
            ->assertSet('content', 'v1')
            ->set('content', 'v2')
            ->call('saveFile')
            ->call('setStatus', 'submitted');

        $this->assertSame('v2', $assignment->draftFiles()->first()->content);
        $this->assertSame(AssignmentStatus::Submitted, $assignment->fresh()->status);
    }

    public function test_redo_queues_a_run_with_the_chosen_model(): void
    {
        $this->actingAs(User::factory()->create());
        $assignment = $this->assignment();

        Livewire::test('pages::assignments.show', ['assignment' => $assignment])
            ->set('redoModel', 'claude-haiku-5-5')
            ->set('redoEffort', 'low')
            ->call('redo')
            ->assertRedirect();

        $run = Run::query()->latest('id')->firstOrFail();
        $this->assertSame(RunType::Redo, $run->type);
        $this->assertSame($assignment->id, $run->assignment_id);
        $this->assertSame('claude-haiku-5-5', $run->model->value);
        $this->assertTrue($run->force);
    }
}
