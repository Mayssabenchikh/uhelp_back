<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class PaymentController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        if ($user && ($user->role === 'admin' || $user->role === 'agent')) {
            return response()->json(Payment::with('user','subscription')->get());
        }
        return response()->json(Payment::with('subscription')->where('user_id', $user->id)->get());
    }

    public function store(StorePaymentRequest $request)
    {
        $data = $request->validated();

        if (empty($data['user_id'])) {
            $data['user_id'] = Auth::id();
        }

        $payment = Payment::create($data);

        // Si paiement complété, update subscription status (simple logique)
        if ($payment->status === 'completed' && $payment->subscription_id) {
            $subscription = Subscription::find($payment->subscription_id);
            if ($subscription) {
                $subscription->status = 'active';
                // si tu veux démarrer une période, tu peux injecter dates ici
                $subscription->save();
            }
        }

        return response()->json($payment->load('subscription','user'), 201);
    }

    public function show(Payment $payment)
    {
        $user = Auth::user();
        if ($user && $user->role === 'client' && $payment->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($payment->load('subscription','user'));
    }

    public function update(StorePaymentRequest $request, Payment $payment)
    {
        $payment->update($request->validated());
        return response()->json($payment->fresh()->load('subscription','user'));
    }

    public function destroy(Payment $payment)
    {
        $payment->delete();
        return response()->json(null, 204);
    }
}
