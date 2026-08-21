<?php

declare(strict_types=1);

namespace App\Controllers\Requests;

use Spartan\FormRequest;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->auth->check();
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|min:3|max:200',
            'description' => 'required|string|min:10',
            'priority'    => 'required|in:low,medium,high',
            'deadline'    => 'nullable|date',
        ];
    }
}
