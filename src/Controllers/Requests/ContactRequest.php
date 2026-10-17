<?php

declare(strict_types=1);

namespace App\Controllers\Requests;

use Spartan\FormRequest;

class ContactRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'    => 'required|string|min:2|max:100',
            'email'   => 'required|email|max:255',
            'message' => 'required|string|min:5|max:5000',
        ];
    }
}
