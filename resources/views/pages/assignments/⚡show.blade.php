<?php

use App\Enums\AssignmentStatus;
use App\Enums\ClaudeModel;
use App\Enums\Effort;
use App\Enums\RunType;
use App\Models\Assignment;
use App\Models\AssignmentFile;
use App\Models\Run;
use App\Models\Setting;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Assignment')] class extends Component {
    public Assignment $assignment;

    public ?int $fileId = null;

    public string $content = '';

    public string $redoModel = '';

    public string $redoEffort = '';

    public function mount(): void
    {
        $settings = Setting::current();
        $this->redoModel = ($this->assignment->model ?? $settings->model)->value;
        $this->redoEffort = ($this->assignment->effort ?? $settings->effort)->value;

        if ($first = $this->assignment->draftFiles->first()) {
            $this->selectFile($first->id);
        }
    }

    #[Computed]
    public function file(): ?AssignmentFile
    {
        return $this->fileId ? $this->assignment->draftFiles()->find($this->fileId) : null;
    }

    #[Computed]
    public function pendingRedo(): ?Run
    {
        return Run::query()->active()->where('assignment_id', $this->assignment->id)->latest('id')->first();
    }

    public function selectFile(int $id): void
    {
        $file = $this->assignment->draftFiles()->findOrFail($id);
        $this->fileId = $file->id;
        $this->content = (string) $file->content;
        unset($this->file);
    }

    public function saveFile(): void
    {
        $this->validate(['content' => ['string', 'max:2000000']]);
        $this->file?->update(['content' => $this->content]);

        Flux::toast(variant: 'success', text: __('Draft saved.'));
    }

    public function setStatus(string $status): void
    {
        $status = AssignmentStatus::from($status);
        abort_unless($status->isManual() || $status === AssignmentStatus::DraftReady, 422);

        $this->assignment->update(['status' => $status]);

        Flux::toast(variant: 'success', text: __('Status updated to :status.', ['status' => $status->label()]));
    }

    public function redo(): void
    {
        $this->validate([
            'redoModel' => ['required', Rule::enum(ClaudeModel::class)],
            'redoEffort' => ['required', Rule::enum(Effort::class)],
        ]);

        if ($this->pendingRedo) {
            Flux::toast(variant: 'warning', text: __('This assignment already has a queued run.'));

            return;
        }

        $run = Run::queue(RunType::Redo, ClaudeModel::from($this->redoModel), Effort::from($this->redoEffort), $this->assignment);
        Flux::modal('redo')->close();
        $this->redirectRoute('runs.show', $run, navigate: true);
    }

    public function markdown(?string $text): string
    {
        return Str::markdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}; ?>

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <flux:text>{{ $assignment->course }}</flux:text>
            <flux:heading size="xl" level="1">{{ $assignment->name }}</flux:heading>
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <flux:badge :color="$assignment->status->color()">{{ $assignment->status->label() }}</flux:badge>
                <flux:badge variant="outline" size="sm">{{ $assignment->module }}</flux:badge>
                <flux:text @class(['text-red-600 dark:text-red-400' => $assignment->isPastDue()])>
                    {{ __('Due') }}: {{ $assignment->due_at?->format('d/m/Y H:i') ?? '—' }}
                </flux:text>
                @if ($assignment->model)
                    <flux:text class="text-xs">
                        · {{ $assignment->model->label() }} / {{ $assignment->effort?->label() }} · {{ $assignment->generated_at?->format('d/m H:i') }}
                    </flux:text>
                @endif
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <flux:button :href="$assignment->url" target="_blank" icon="arrow-top-right-on-square" size="sm">{{ __('Open in Moodle') }}</flux:button>
            <flux:modal.trigger name="redo">
                <flux:button icon="arrow-path" size="sm" :disabled="(bool) $this->pendingRedo">{{ __('Redo') }}</flux:button>
            </flux:modal.trigger>
            @if ($assignment->status !== AssignmentStatus::Reviewed)
                <flux:button icon="eye" size="sm" wire:click="setStatus('reviewed')">{{ __('Mark reviewed') }}</flux:button>
            @endif
            @if ($assignment->status !== AssignmentStatus::Submitted)
                <flux:button icon="check" size="sm" variant="primary" wire:click="setStatus('submitted')">{{ __('Mark submitted') }}</flux:button>
            @else
                <flux:button icon="arrow-uturn-left" size="sm" wire:click="setStatus('draft_ready')">{{ __('Reopen') }}</flux:button>
            @endif
        </div>
    </div>

    @if ($this->pendingRedo)
        <flux:callout icon="arrow-path" color="blue">
            <flux:callout.heading>{{ __('A run for this assignment is :status.', ['status' => mb_strtolower($this->pendingRedo->status->label())]) }}</flux:callout.heading>
            <x-slot name="actions">
                <flux:button size="sm" :href="route('runs.show', $this->pendingRedo)" wire:navigate>{{ __('View log') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endif

    @if ($assignment->error)
        <flux:callout icon="exclamation-triangle" variant="danger" :heading="__('Last error')" :text="$assignment->error" />
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Statement') }}</flux:heading>
            <div class="max-h-[50vh] overflow-auto whitespace-pre-wrap text-sm">{{ $assignment->statement_md }}</div>

            @if ($assignment->attachments->isNotEmpty())
                <flux:separator />
                <flux:subheading>{{ __('Attachments') }}</flux:subheading>
                <ul class="space-y-1">
                    @foreach ($assignment->attachments as $attachment)
                        <li>
                            <flux:link :href="route('assignments.files.download', [$assignment, $attachment])" class="text-sm">
                                <flux:icon.paper-clip variant="micro" class="inline" /> {{ $attachment->path }}
                            </flux:link>
                        </li>
                    @endforeach
                </ul>
            @endif
        </flux:card>

        <flux:card class="space-y-3">
            <flux:heading size="lg">{{ __('Review notes') }}</flux:heading>
            @if ($assignment->summary)
                <flux:text class="italic">{{ $assignment->summary }}</flux:text>
            @endif
            <div class="markdown max-h-[50vh] overflow-auto text-sm">
                {!! $this->markdown($assignment->review_notes ?: __('No draft generated yet.')) !!}
            </div>
        </flux:card>
    </div>

    <flux:card class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <flux:heading size="lg">{{ __('Draft') }}</flux:heading>
            @if ($assignment->draftFiles->isNotEmpty())
                <flux:button size="sm" icon="arrow-down-tray" :href="route('assignments.zip', $assignment)">{{ __('Download .zip') }}</flux:button>
            @endif
        </div>

        @if ($assignment->draftFiles->isEmpty())
            <flux:text>{{ __('No draft files yet.') }}</flux:text>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($assignment->draftFiles as $draft)
                    <flux:button size="sm" wire:key="draft-{{ $draft->id }}" wire:click="selectFile({{ $draft->id }})"
                        :variant="$draft->id === $fileId ? 'primary' : 'outline'" icon="document-text">
                        {{ $draft->path }}
                    </flux:button>
                @endforeach
            </div>

            @if ($this->file)
                <form wire:submit="saveFile" class="space-y-3">
                    <flux:textarea wire:model="content" rows="24" class="font-mono text-sm" resize="vertical" />
                    <div class="flex gap-2">
                        <flux:button type="submit" variant="primary" size="sm">{{ __('Save') }}</flux:button>
                        <flux:button size="sm" variant="ghost" icon="arrow-down-tray"
                            :href="route('assignments.files.download', [$assignment, $this->file])">{{ __('Download file') }}</flux:button>
                    </div>
                </form>
            @endif
        @endif
    </flux:card>

    <flux:modal name="redo" class="md:w-96">
        <form wire:submit="redo" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Redo assignment') }}</flux:heading>
                <flux:text class="mt-2">{{ __('The current draft will be replaced, including your edits.') }}</flux:text>
            </div>
            <flux:select wire:model="redoModel" :label="__('Model')">
                @foreach (ClaudeModel::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model="redoEffort" :label="__('Effort')">
                @foreach (Effort::cases() as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Queue') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
