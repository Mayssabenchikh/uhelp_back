<?php

namespace App\Http\Controllers;

use App\Services\GeminiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiController extends Controller
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'context' => 'required|string',
            'language' => 'nullable|string|max:10'
        ]);

        try {
            if (empty(config('services.gemini.key', env('GEMINI_API_KEY', '')))) {
                Log::error('Gemini suggest called but GEMINI_API_KEY not configured');
                return response()->json(['success' => false, 'message' => 'Gemini API key not configured on server.'], 500);
            }

            $suggestion = $this->gemini->suggestReply($validated['context']);

            return response()->json(['success' => true, 'suggestion' => $suggestion]);
        } catch (Throwable $e) {
            Log::error('Gemini suggest error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Unable to generate suggestion.', 'error' => $e->getMessage()], 500);
        }
    }

    public function translate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string',
            'to' => 'required|string|max:10',
        ]);

        try {
            $translation = $this->gemini->translate($validated['text'], $validated['to']);

            return response()->json(['success' => true, 'translation' => $translation]);
        } catch (Throwable $e) {
            Log::error('Gemini translate error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Unable to translate text.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Génère des FAQ / résumé / tags depuis un ticket.
     */
    public function generateFaq(Request $request): JsonResponse
    {
        try {
            $ticketContent = $request->input('content');

            if (!$ticketContent) {
                return response()->json(['success' => false, 'message' => 'Content is required'], 422);
            }

            $raw = $this->gemini->faqFromTicket($ticketContent);

            // Try to decode JSON (we asked the model to return JSON)
            $maybe = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($maybe)) {
                return response()->json(['success' => true, 'faq' => $maybe]);
            }

            // If not valid JSON, return raw text under faq_text for debugging
            return response()->json([
                'success' => true,
                'faq_text' => $raw,
                'message' => 'Model did not return structured JSON — see faq_text.'
            ]);
        } catch (Throwable $e) {
            Log::error('Gemini Error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Unable to generate FAQ', 'error' => $e->getMessage()], 500);
        }
    }
}
