<?php

declare(strict_types=1);

namespace App\Controllers\Requests;

use Spartan\FormRequest;

class StoreReplyRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'body'     => 'required|string|min:2|max:10000',
            'internal' => 'nullable|in:1',
        ];
    }
}
