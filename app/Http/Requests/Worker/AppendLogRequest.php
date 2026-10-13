<?php

namespace App\Http\Requests\Worker;

use Illuminate\Foundation\Http\FormRequest;

class AppendLogRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['present', 'array', 'max:500'],
            'lines.*' => ['string', 'max:5000'],
        ];
    }
}
