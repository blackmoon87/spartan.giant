<?php

declare(strict_types=1);

namespace App\Controllers\Requests;

use Spartan\FormRequest;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->session->get('role');
        return $role === 'admin' || $role === 'author';
    }

    public function rules(): array
    {
        return [
            'title'       => 'required|string|min:5|max:200',
            'category_id' => 'required|integer',
            'excerpt'     => 'required|string|min:10',
            'content'     => 'required|string|min:20',
        ];
    }
}
