<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionRequest;
use App\Http\Requests\UpdateSubscriptionRequest;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user && in_array($user->role, ['admin','agent'])) {
            return response()->json(Subscription::with('plan','user')->get());
        }
        return response()->json(Subscription::with('plan')->where('user_id', $user->id)->get());
    }

    public function store(StoreSubscriptionRequest $request)
    {
        $data = $request->validated();
        $userId = $data['user_id'] ?? Auth::id();

        $hasActive = Subscription::where('user_id', $userId)
            ->where('status', 'active')
            ->where('current_period_ends_at', '>', now())
            ->exists();

        if ($hasActive) {
            return response()->json(['message' => 'Vous avez déjà une souscription active.'], 422);
        }

        $subscription = Subscription::create(array_merge($data, [
            'user_id' => $userId,
            'status' => $data['status'] ?? 'pending',
            'tickets_used' => $data['tickets_used'] ?? 0,
        ]));

        return response()->json($subscription->load('plan'), 201);
    }

    public function show(Subscription $subscription)
    {
        $user = Auth::user();
        if ($user && $user->role === 'client' && $subscription->user_id !== $user->id) {
            return response()->json(['message'=>'Unauthorized'],403);
        }
        return response()->json($subscription->load('plan','user'));
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription)
    {
        $user = Auth::user();
        if ($user && $user->role === 'client' && $subscription->user_id !== $user->id) {
            return response()->json(['message'=>'Unauthorized'],403);
        }
        $subscription->update($request->validated());
        return response()->json($subscription->fresh()->load('plan','user'));
    }

    public function destroy(Subscription $subscription)
    {
        $user = Auth::user();
        if ($user && $user->role === 'client' && $subscription->user_id !== $user->id) {
            return response()->json(['message'=>'Unauthorized'],403);
        }
        $subscription->markCancelled();
        return response()->json(null,204);
    }
}
