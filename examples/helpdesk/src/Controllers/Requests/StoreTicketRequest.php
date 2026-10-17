<?php

declare(strict_types=1);

namespace App\Controllers\Requests;

use Spartan\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subject'  => 'required|string|min:5|max:200',
            'body'     => 'required|string|min:10|max:10000',
            'priority' => 'required|in:low,normal,high,urgent',
        ];
    }
}
