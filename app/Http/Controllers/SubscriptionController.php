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
        if ($user && ($user->role === 'admin' || $user->role === 'agent')) {
            return response()->json(Subscription::with('plan','user')->get());
        }
        // client only their subscriptions
        return response()->json(Subscription::with('plan')->where('user_id', $user->id)->get());
    }

    public function store(StoreSubscriptionRequest $request)
    {
        $data = $request->validated();

        // si user_id non fourni, on l'attache au user connecté
        if (empty($data['user_id'])) {
            $data['user_id'] = Auth::id();
        }

        $subscription = Subscription::create($data);

        return response()->json($subscription->load('plan'), 201);
    }

    public function show(Subscription $subscription)
    {
        // basic access control: clients can only voir leurs subscriptions
        $user = Auth::user();
        if ($user && $user->role === 'client' && $subscription->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($subscription->load('plan','user'));
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription)
    {
        $user = Auth::user();
        if ($user && $user->role === 'client' && $subscription->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $subscription->update($request->validated());

        return response()->json($subscription->fresh()->load('plan','user'));
    }

    public function destroy(Subscription $subscription)
    {
        $user = Auth::user();
        if ($user && $user->role === 'client' && $subscription->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $subscription->delete();
        return response()->json(null, 204);
    }
}
