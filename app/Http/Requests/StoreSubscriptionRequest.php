<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'user_id' => 'nullable|exists:users,id',
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'status' => 'nullable|in:pending,active,cancelled,past_due,exhausted',
            'current_period_started_at' => 'nullable|date',
            'current_period_ends_at' => 'nullable|date|after_or_equal:current_period_started_at',
            'tickets_used' => 'nullable|integer|min:0',
            'provider_subscription_id' => 'nullable|string|max:255',
        ];
    }
}
