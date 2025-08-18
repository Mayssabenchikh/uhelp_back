<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionPlanController extends Controller
{
    public function index()
    {
        return response()->json(SubscriptionPlan::all());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'slug' => 'required|string|max:191|unique:subscription_plans,slug',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => ['required', Rule::in(['monthly','yearly','one_time'])],
            'ticket_limit' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
        ]);

        $data['features'] = $data['features'] ?? [];
        $plan = SubscriptionPlan::create($data);

        return response()->json($plan, 201);
    }

    public function show(SubscriptionPlan $subscriptionPlan)
    {
        return response()->json($subscriptionPlan);
    }

    public function update(Request $request, SubscriptionPlan $subscriptionPlan)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'slug' => 'sometimes|string|max:191|unique:subscription_plans,slug,' . $subscriptionPlan->id,
            'price' => 'sometimes|numeric|min:0',
            'billing_cycle' => ['sometimes', Rule::in(['monthly','yearly','one_time'])],
            'ticket_limit' => 'nullable|integer|min:0',
            'features' => 'nullable|array',
        ]);

        if (isset($data['features'])) {
            $subscriptionPlan->features = $data['features'];
        }

        $subscriptionPlan->fill($data);
        $subscriptionPlan->save();

        return response()->json($subscriptionPlan);
    }

    public function destroy(SubscriptionPlan $subscriptionPlan)
    {
        $subscriptionPlan->delete();
        return response()->json(null, 204);
    }
}
