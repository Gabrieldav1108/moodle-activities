<?php

use App\Enums\SolverEngine;
use App\Models\Run;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Runs')] class extends Component {
    use WithPagination;

    #[Computed]
    public function runs()
    {
        return Run::query()->with('assignment')->latest('id')->paginate(20);
    }
}; ?>

<div class="flex flex-col gap-6" wire:poll.5s>
    <flux:heading size="xl" level="1">{{ __('Runs') }}</flux:heading>

    <flux:table :paginate="$this->runs">
        <flux:table.columns>
            <flux:table.column>#</flux:table.column>
            <flux:table.column>{{ __('Type') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Model') }}</flux:table.column>
            <flux:table.column>{{ __('Created') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Duration') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->runs as $run)
                <flux:table.row :key="$run->id">
                    <flux:table.cell>
                        <flux:link :href="route('runs.show', $run)" wire:navigate>{{ $run->id }}</flux:link>
                    </flux:table.cell>
                    <flux:table.cell class="whitespace-normal">
                        {{ $run->type->label() }}
                        @if ($run->assignment)
                            <flux:text class="text-xs">{{ $run->assignment->name }}</flux:text>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$run->status->color()" size="sm">{{ $run->status->label() }}</flux:badge>
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden">{{ SolverEngine::modelLabel($run->model) }} · {{ $run->effort->label() }}</flux:table.cell>
                    <flux:table.cell>{{ $run->created_at->format('d/m H:i') }}</flux:table.cell>
                    <flux:table.cell class="max-md:hidden">
                        {{ $run->started_at && $run->finished_at ? $run->started_at->diffForHumans($run->finished_at, true) : '—' }}
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6">{{ __('No runs yet.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>
