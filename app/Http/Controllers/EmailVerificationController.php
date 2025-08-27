<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    /**
     * Vérifier l'email depuis le lien signé envoyé par e-mail.
     * Route : GET /api/email/verify/{id}/{hash}
     * IMPORTANT: cette route NE DOIT PAS avoir le middleware auth:sanctum
     *           elle doit être protégée par 'signed' et éventuellement 'throttle'.
     */
    public function verify(Request $request, $id, $hash)
    {
        // Vérifier signature + expiration
        if (! $request->hasValidSignature()) {
            return response()->json(['status' => false, 'message' => 'Invalid or expired verification link.'], 403);
        }

        // Récupérer l'utilisateur
        $user = User::findOrFail($id);

        // Vérifier le hash (comme fait par Laravel)
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['status' => false, 'message' => 'Invalid verification data.'], 403);
        }

        // Déjà vérifié ?
        if ($user->hasVerifiedEmail()) {
            return response()->json(['status' => true, 'message' => 'Email already verified.']);
        }

        // Marquer comme vérifié
        $user->email_verified_at = now();
        $user->save();

        // Déclencher l'événement Verified (utile si tu as des listeners)
        event(new Verified($user));

        return response()->json(['status' => true, 'message' => 'Email verified successfully.']);
    }

    /**
     * Renvoi un lien de vérification (si besoin)
     */
    public function resend(Request $request)
    {
        // Ici on attendra authentification pour renvoyer (ex : auth:sanctum)
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['status' => true, 'message' => 'Email already verified.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['status' => true, 'message' => 'Verification link sent.']);
    }
}
