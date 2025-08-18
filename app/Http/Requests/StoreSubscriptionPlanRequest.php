<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubscriptionPlanRequest extends FormRequest
{
    public function authorize() { return true; }

    public function rules()
    {
        return [
            'name' => 'required|string|max:191',
            'slug' => 'required|string|max:191|unique:subscription_plans,slug',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,yearly,one_time',
            'ticket_limit' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
        ];
    }
}
