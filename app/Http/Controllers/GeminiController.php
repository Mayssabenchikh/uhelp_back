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

            // Try to extract JSON candidate from fences or inline JSON before decoding
            $candidate = null;
            if (preg_match('/```json\s*([\s\S]*?)\s*```/i', $raw, $m)) {
                $candidate = $m[1];
            } elseif (preg_match('/(\[.*\]|\{.*\})/s', $raw, $m)) {
                $candidate = $m[1];
            } else {
                $candidate = $raw;
            }

            // Try to decode JSON candidate (already extracted above)
            $maybe = json_decode($candidate, true);

            // If AI returned structured array/object, store generated FAQs into pending table for admin validation
            if (json_last_error() === JSON_ERROR_NONE && is_array($maybe) && count($maybe) > 0) {
                // If an array of pairs, create one PendingFaq for each pair
                $created = [];
                $max = 10; // safety limit
                $count = 0;
                foreach ($maybe as $item) {
                    if ($count++ >= $max) break;
                    $q = isset($item['question']) ? trim($item['question']) : (isset($item['q']) ? trim($item['q']) : null);
                    $a = isset($item['answer']) ? trim($item['answer']) : (isset($item['a']) ? trim($item['a']) : null);
                    if (empty($q) || empty($a)) continue;

                    try {
                        $pf = \App\Models\PendingFaq::create([
                            'question' => $q,
                            'answer' => $a,
                            'language' => $request->input('language', 'en'),
                            'category' => $request->input('category', null),
                            'user_id' => $request->user()?->id ?? null,
                            'status' => 'pending',
                            'raw_model_output' => json_encode($maybe),
                        ]);
                        $created[] = $pf;
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error('Failed to save generated pending FAQ item: '.$e->getMessage());
                    }
                }

                // If we created at least one record, return the generated items
                if (count($created) > 0) {
                    return response()->json(['success' => true, 'faq' => $maybe, 'created' => count($created)]);
                }

                // Fallback: if no records created, still return the parsed array for debug
                return response()->json(['success' => true, 'faq' => $maybe]);
            }

            // If extraction attempt failed, try decoding the full raw text (last resort)
            $maybe2 = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($maybe2) && count($maybe2) > 0) {
                try {
                    \App\Models\PendingFaq::create([
                        'question' => $ticketContent,
                        'answer' => is_string($maybe2[0]['answer'] ?? null) ? ($maybe2[0]['answer'] ?? '') : json_encode($maybe2[0]),
                        'language' => $request->input('language', 'en'),
                        'category' => $request->input('category', null),
                        'user_id' => $request->user()?->id ?? null,
                        'status' => 'pending',
                        'raw_model_output' => $raw,
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to save pending FAQ (raw decode): '.$e->getMessage());
                }

                return response()->json(['success' => true, 'faq_text' => $raw, 'message' => 'Model returned JSON in raw output — see faq_text.']);
            }

            // Not valid JSON: save raw output but try to clean the answer field for better admin display
            try {
                $clean = preg_replace('/```[\s\S]*?```/m', '', $raw); // remove fenced blocks for readability
                $clean = trim($clean);
                // keep reasonable length for the answer column but preserve raw_model_output for full data
                $answerSnippet = mb_substr($clean, 0, 2000);

                \App\Models\PendingFaq::create([
                    'question' => $ticketContent,
                    'answer' => $answerSnippet,
                    'language' => $request->input('language', 'en'),
                    'category' => $request->input('category', null),
                    'user_id' => $request->user()?->id ?? null,
                    'status' => 'pending',
                    'raw_model_output' => $raw,
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to save pending FAQ (raw): '.$e->getMessage());
            }

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
