<?php

declare(strict_types=1);

namespace App\Controllers\Requests;

use Spartan\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->session->get('role');
        return $role === 'admin';
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|min:3|max:100',
            'category_id' => 'required|integer',
            'price'       => 'required|numeric|min:0.01',
            'stock'       => 'required|integer|min:0',
            'description' => 'required|string|min:10',
        ];
    }
}
