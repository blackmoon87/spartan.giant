<?php

declare(strict_types=1);

namespace App\Controllers\Requests;

use Spartan\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'post_id'      => 'required|integer',
            'author_name'  => 'required|string|min:2|max:100',
            'author_email' => 'required|email',
            'content'      => 'required|string|min:5',
        ];
    }
}
