<?php

namespace App\Http\Requests\Worker;

use App\Enums\AssignmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UploadAssignmentRequest extends FormRequest
{
    /**
     * Multipart sends "meta" and "draft" as JSON strings; decode them before validating.
     */
    protected function prepareForValidation(): void
    {
        foreach (['meta', 'draft'] as $key) {
            if (is_string($this->input($key))) {
                $this->merge([$key => json_decode($this->input($key), true)]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'meta' => ['required', 'array'],
            'meta.cmid' => ['required', 'integer', 'min:1'],
            'meta.event_id' => ['nullable', 'integer'],
            'meta.name' => ['required', 'string', 'max:255'],
            'meta.course' => ['required', 'string', 'max:255'],
            'meta.module' => ['required', 'string', 'max:50'],
            'meta.due_at' => ['nullable', 'integer'],
            'meta.overdue' => ['boolean'],
            'meta.url' => ['required', 'url', 'max:500'],
            'meta.statement_md' => ['nullable', 'string'],
            'meta.statement_html' => ['nullable', 'string'],
            'meta.summary' => ['nullable', 'string'],
            'meta.review_notes' => ['nullable', 'string'],
            'meta.status' => ['required', Rule::in([
                AssignmentStatus::Downloaded->value,
                AssignmentStatus::DraftReady->value,
                AssignmentStatus::Failed->value,
            ])],
            'meta.error' => ['nullable', 'string'],
            'draft' => ['nullable', 'array', 'max:100'],
            'draft.*.path' => ['required', 'string', 'max:255'],
            'draft.*.content' => ['present', 'nullable', 'string'],
            'attachments' => ['nullable', 'array', 'max:50'],
            'attachments.*' => ['file', 'max:51200'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach ($this->input('draft', []) ?? [] as $i => $file) {
                    if (! self::isSafePath($file['path'] ?? '')) {
                        $validator->errors()->add("draft.$i.path", 'Unsafe path.');
                    }
                }
            },
        ];
    }

    public static function isSafePath(string $path): bool
    {
        $path = str_replace('\\', '/', $path);

        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! preg_match('/^[a-zA-Z]:/', $path)
            && ! in_array('..', explode('/', $path), true);
    }
}
