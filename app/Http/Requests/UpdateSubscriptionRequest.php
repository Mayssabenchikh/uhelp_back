<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Autorise si l'utilisateur est connecté (tu peux ajouter des policies plus fines).
    return Auth::check(); // ou Auth::user()->can('something'), Gate::allows(...)
    }

    public function rules(): array
    {
        return [
            'subscription_plan_id' => 'sometimes|exists:subscription_plans,id',
            'status' => 'sometimes|in:pending,active,cancelled,past_due',
            'current_period_started_at' => 'sometimes|date',
            'current_period_ends_at' => 'sometimes|date|after_or_equal:current_period_started_at',
            'tickets_used' => 'sometimes|integer|min:0',
            'provider_subscription_id' => 'nullable|string|max:255',
        ];
    }
}
