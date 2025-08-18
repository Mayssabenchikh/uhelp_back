<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'subscription_plan_id' => 'nullable|exists:subscription_plans,id',
            'amount' => ['sometimes','nullable','integer','between:1,100000000'],
            'currency' => 'required|string|in:TND,EUR,USD',
            'description' => 'nullable|string|max:500',
            'provider_payment_id' => 'nullable|string|max:255',
        ];
    }
}
