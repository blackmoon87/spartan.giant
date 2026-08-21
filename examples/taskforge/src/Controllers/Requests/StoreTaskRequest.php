<?php

declare(strict_types=1);

namespace App\Controllers\Requests;

use Spartan\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->auth->check();
    }

    public function rules(): array
    {
        return [
            'title'       => 'required|string|min:3|max:255',
            'description' => 'nullable|string',
            'project_id'  => 'required|integer',
            'assigned_to' => 'required|integer',
            'priority'    => 'required|in:low,medium,high',
            'due_date'    => 'nullable|date',
        ];
    }
}
