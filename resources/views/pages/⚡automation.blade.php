<?php

use App\Enums\ClaudeModel;
use App\Enums\Effort;
use App\Models\Setting;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Automation')] class extends Component {
    public bool $enabled = false;

    /** @var list<string> */
    public array $run_times = [];

    /** @var list<int|string> */
    public array $weekdays = [];

    public string $model = '';

    public string $effort = '';

    public int $days_back = 30;

    public int $days_ahead = 120;

    public string $moodle_url = '';

    public string $moodle_username = '';

    public string $moodle_password = '';

    public string $anthropic_api_key = '';

    public function mount(): void
    {
        $settings = Setting::current();

        $this->fill($settings->only(['enabled', 'run_times', 'weekdays', 'days_back', 'days_ahead', 'moodle_url']));
        $this->model = $settings->model->value;
        $this->effort = $settings->effort->value;
        $this->moodle_username = (string) $settings->moodle_username;
    }

    public function addTime(): void
    {
        $this->run_times[] = '12:00';
    }

    public function removeTime(int $index): void
    {
        unset($this->run_times[$index]);
        $this->run_times = array_values($this->run_times);
    }

    public function save(): void
    {
        $validated = $this->validate([
            'enabled' => ['boolean'],
            'run_times' => ['array', 'max:24'],
            'run_times.*' => ['required', 'date_format:H:i', 'distinct'],
            'weekdays' => ['array'],
            'weekdays.*' => ['integer', 'between:0,6'],
            'model' => ['required', Rule::enum(ClaudeModel::class)],
            'effort' => ['required', Rule::enum(Effort::class)],
            'days_back' => ['required', 'integer', 'between:0,365'],
            'days_ahead' => ['required', 'integer', 'between:1,365'],
            'moodle_url' => ['required', 'url'],
            'moodle_username' => ['nullable', 'string', 'max:255'],
            'moodle_password' => ['nullable', 'string', 'max:255'],
            'anthropic_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['run_times'] = collect($validated['run_times'])->sort()->values()->all();
        $validated['weekdays'] = collect($validated['weekdays'])->map(fn ($day) => (int) $day)->sort()->values()->all();
        $validated['moodle_url'] = rtrim($validated['moodle_url'], '/');

        // Secrets are write-only: an empty field keeps the stored value.
        foreach (['moodle_password', 'anthropic_api_key'] as $secret) {
            if (blank($validated[$secret])) {
                unset($validated[$secret]);
            }
        }

        $this->settings->update($validated);
        unset($this->settings);

        $this->run_times = $validated['run_times'];
        $this->reset('moodle_password', 'anthropic_api_key');

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    #[Computed]
    public function settings(): Setting
    {
        return Setting::current();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function days(): array
    {
        return [0 => __('Sun'), 1 => __('Mon'), 2 => __('Tue'), 3 => __('Wed'), 4 => __('Thu'), 5 => __('Fri'), 6 => __('Sat')];
    }
}; ?>

<div class="flex max-w-3xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Automation') }}</flux:heading>
        <flux:text class="mt-1">{{ __('When the worker runs, which model it uses and the access credentials.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:card class="space-y-6">
            <flux:heading size="lg">{{ __('Schedule') }}</flux:heading>

            <flux:switch wire:model="enabled" :label="__('Run automatically')" align="left" />

            <flux:fieldset>
                <flux:legend>{{ __('Run times') }}</flux:legend>
                <div class="flex flex-wrap gap-3">
                    @foreach ($run_times as $index => $time)
                        <div class="flex items-center gap-1" wire:key="time-{{ $index }}">
                            <flux:input type="time" wire:model="run_times.{{ $index }}" class="w-32" />
                            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="removeTime({{ $index }})" :aria-label="__('Remove')" />
                        </div>
                    @endforeach
                    <flux:button size="sm" icon="plus" wire:click="addTime">{{ __('Add time') }}</flux:button>
                </div>
                <flux:error name="run_times.*" />
            </flux:fieldset>

            <flux:checkbox.group wire:model="weekdays" :label="__('Weekdays')" class="flex flex-wrap gap-4">
                @foreach ($this->days as $value => $label)
                    <flux:checkbox :value="$value" :label="$label" />
                @endforeach
            </flux:checkbox.group>

            @if ($upcoming = $this->settings->upcomingRuns())
                <flux:text>
                    {{ __('Next runs') }}:
                    {{ collect($upcoming)->map(fn ($at) => $at->translatedFormat('D d/m H:i'))->join(', ') }}
                </flux:text>
            @endif
        </flux:card>

        <flux:card class="space-y-6">
            <flux:heading size="lg">{{ __('AI model') }}</flux:heading>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="model" :label="__('Model')">
                    @foreach (ClaudeModel::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="effort" :label="__('Effort')">
                    @foreach (Effort::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input type="number" wire:model="days_back" :label="__('Include overdue up to (days)')" min="0" />
                <flux:input type="number" wire:model="days_ahead" :label="__('Look ahead (days)')" min="1" />
            </div>
        </flux:card>

        <flux:card class="space-y-6">
            <flux:heading size="lg">{{ __('Credentials') }}</flux:heading>

            <flux:input wire:model="moodle_url" :label="__('Moodle URL')" type="url" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="moodle_username" :label="__('Moodle username')" autocomplete="off" />
                <flux:input wire:model="moodle_password" :label="__('Moodle password')" type="password" viewable autocomplete="new-password"
                    :placeholder="filled($this->settings->moodle_password) ? '•••••••• '.__('set') : __('not set')"
                    :description="__('Leave blank to keep the current one.')" />
            </div>
            <flux:input wire:model="anthropic_api_key" :label="__('Anthropic API key')" type="password" viewable autocomplete="off"
                :placeholder="filled($this->settings->anthropic_api_key) ? '•••••••• '.__('set') : __('not set')"
                :description="__('Leave blank to keep the current one.')" />

            <flux:input :value="config('services.worker.token')" :label="__('Worker token')" readonly copyable
                :description="__('Copy into WORKER_TOKEN in worker/.env (defined in the app .env).')" />
        </flux:card>

        <div>
            <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
        </div>
    </form>
</div>
