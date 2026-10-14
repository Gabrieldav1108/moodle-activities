<?php

use App\Enums\RunStatus;
use App\Enums\SolverEngine;
use App\Models\Run;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Run')] class extends Component {
    public Run $run;

    public function cancel(): void
    {
        if ($this->run->status === RunStatus::Pending) {
            $this->run->update(['status' => RunStatus::Failed, 'error' => __('Cancelled'), 'finished_at' => now()]);
            Flux::toast(text: __('Run cancelled.'));
        }
    }
}; ?>

<div class="flex flex-col gap-6" @if ($run->status->isActive()) wire:poll.2s @endif>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Run') }} #{{ $run->id }}</flux:heading>
            <flux:text class="mt-1">
                {{ $run->type->label() }} · {{ $run->engine->label() }} · {{ SolverEngine::modelLabel($run->model) }} · {{ __('Effort') }}: {{ $run->effort->label() }}
                · {{ $run->created_at->format('d/m/Y H:i') }}
            </flux:text>
        </div>
        <div class="flex items-center gap-2">
            <flux:badge :color="$run->status->color()">{{ $run->status->label() }}</flux:badge>
            @if ($run->status === RunStatus::Pending)
                <flux:button size="sm" variant="ghost" wire:click="cancel">{{ __('Cancel') }}</flux:button>
            @endif
        </div>
    </div>

    @if ($run->assignment)
        <flux:text>
            {{ __('Assignment') }}:
            <flux:link :href="route('assignments.show', $run->assignment)" wire:navigate>{{ $run->assignment->name }}</flux:link>
        </flux:text>
    @endif

    @if ($run->status === RunStatus::Pending)
        <flux:callout icon="clock" variant="secondary" :heading="__('Waiting for the worker to pick up this run.')" />
    @endif

    @if ($run->error)
        <flux:callout icon="exclamation-triangle" variant="danger" :heading="$run->error" />
    @endif

    <flux:card class="p-0">
        <pre class="max-h-[60vh] overflow-auto whitespace-pre-wrap break-words p-4 font-mono text-xs leading-relaxed">{{ $run->log ?: __('No output yet.') }}</pre>
    </flux:card>
</div>
