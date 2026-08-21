<?php

declare(strict_types=1);

namespace App\Controllers\Requests;

use Spartan\FormRequest;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shipping_address' => 'required|string|min:10|max:255',
            'payment_method'   => 'required|string|in:credit_card,paypal,apple_pay',
        ];
    }
}
