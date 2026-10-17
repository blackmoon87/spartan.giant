<?php

declare(strict_types=1);

namespace App\Controllers\Requests;

use Spartan\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status'      => 'required|in:open,pending,resolved,closed',
            'assignee_id' => 'nullable|integer',
        ];
    }
}
