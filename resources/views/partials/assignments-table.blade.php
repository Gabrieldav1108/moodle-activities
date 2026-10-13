@if ($assignments->isEmpty())
    <flux:text>{{ __('No assignments yet. Run the worker to fetch them from Moodle.') }}</flux:text>
@else
    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Assignment') }}</flux:table.column>
            <flux:table.column class="max-md:hidden">{{ __('Course') }}</flux:table.column>
            <flux:table.column>{{ __('Due') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($assignments as $assignment)
                <flux:table.row :key="$assignment->id">
                    <flux:table.cell class="whitespace-normal">
                        <flux:link :href="route('assignments.show', $assignment)" wire:navigate>{{ $assignment->name }}</flux:link>
                        <flux:text class="text-xs">{{ $assignment->module }}</flux:text>
                    </flux:table.cell>
                    <flux:table.cell class="max-md:hidden whitespace-normal">{{ $assignment->course }}</flux:table.cell>
                    <flux:table.cell @class(['text-red-600 dark:text-red-400 font-medium' => $assignment->isPastDue() && ! $assignment->status->isManual()])>
                        {{ $assignment->due_at?->format('d/m/Y H:i') ?? '—' }}
                    </flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$assignment->status->color()" size="sm">{{ $assignment->status->label() }}</flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
@endif
