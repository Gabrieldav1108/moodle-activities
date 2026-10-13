<?php

namespace App\Http\Requests\Worker;

use App\Enums\RunStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FinishRunRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([RunStatus::Completed->value, RunStatus::Failed->value])],
            'error' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
