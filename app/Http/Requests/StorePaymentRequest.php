<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Requiert utilisateur connecté.
    return Auth::check(); // ou Auth::user()->can('something'), Gate::allows(...)
    }

    public function rules(): array
    {
        return [
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'status' => 'required|in:pending,completed,failed',
            'provider_payment_id' => 'nullable|string|max:255',
        ];
    }
}
