<?php

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Assignments')] class extends Component {
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $course = '';

    #[Url]
    public string $search = '';

    public function updated(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function courses()
    {
        return Assignment::query()->distinct()->orderBy('course')->pluck('course');
    }

    #[Computed]
    public function assignments()
    {
        return Assignment::query()
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->course !== '', fn ($query) => $query->where('course', $this->course))
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->orderByRaw('due_at is null, due_at desc')
            ->paginate(25);
    }
}; ?>

<div class="flex flex-col gap-6">
    <flux:heading size="xl" level="1">{{ __('Assignments') }}</flux:heading>

    <div class="grid gap-3 sm:grid-cols-3">
        <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search')" />
        <flux:select wire:model.live="status">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach (AssignmentStatus::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="course">
            <flux:select.option value="">{{ __('All courses') }}</flux:select.option>
            @foreach ($this->courses as $option)
                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @include('partials.assignments-table', ['assignments' => $this->assignments])

    {{ $this->assignments->links() }}
</div>
