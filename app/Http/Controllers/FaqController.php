<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Services\GeminiService;
use App\Models\PendingFaq;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class FaqController extends Controller
{
    protected GeminiService $gemini;

    public function __construct(GeminiService $gemini)
    {
        $this->gemini = $gemini;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Faq::query()->where('is_active', true);

        if ($request->filled('language')) {
            $query->where('language', $request->input('language'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('question', 'like', "%{$s}%")->orWhere('answer', 'like', "%{$s}%");
            });
        }

        $faqs = $query->orderBy('id', 'desc')->paginate(20);

        return response()->json(['success' => true, 'data' => $faqs]);
    }

    public function show($id): JsonResponse
    {
        $faq = Faq::find($id);
        if (! $faq) return response()->json(['success' => false, 'message' => 'FAQ not found'], 404);
        return response()->json(['success' => true, 'data' => $faq]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string',
            'answer' => 'required|string',
            'language' => 'nullable|string|max:10',
            'category' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        $faq = Faq::create(array_merge($validated, ['is_active' => $validated['is_active'] ?? true]));
        return response()->json(['success' => true, 'data' => $faq], 201);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $faq = Faq::find($id);
        if (! $faq) return response()->json(['success' => false, 'message' => 'FAQ not found'], 404);

        $validated = $request->validate([
            'question' => 'nullable|string',
            'answer' => 'nullable|string',
            'language' => 'nullable|string|max:10',
            'category' => 'nullable|string',
            'is_active' => 'nullable|boolean'
        ]);

        $faq->update($validated);
        return response()->json(['success' => true, 'data' => $faq]);
    }

    public function destroy($id): JsonResponse
    {
        $faq = Faq::find($id);
        if (! $faq) return response()->json(['success' => false, 'message' => 'FAQ not found'], 404);
        $faq->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Match a user's question to the best FAQ in the DB and return the answer.
     * Public endpoint: POST /api/faqs/match { question, language? }
     * If no confident DB match is found, fallback to Gemini to generate an answer.
     */
    public function match(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => 'required|string',
            'language' => 'nullable|string|max:10',
        ]);

        $q = trim($validated['question']);
        $query = Faq::query()->where('is_active', true);
        if (!empty($validated['language'])) {
            $query->where('language', $validated['language']);
        }

        // Basic candidate selection by LIKE on question/answer
        $candidates = $query->where(function ($qq) use ($q) {
            $qq->where('question', 'like', "%{$q}%")->orWhere('answer', 'like', "%{$q}%");
        })->limit(50)->get();

        // If no LIKE candidates, broaden search to recent faqs
        if ($candidates->isEmpty()) {
            $candidates = $query->orderBy('id', 'desc')->limit(50)->get();
        }

        $best = null;
        $bestScore = 0;
        $lowerQ = mb_strtolower($q);

        foreach ($candidates as $faq) {
            $text1 = mb_strtolower($faq->question ?? '');
            $text2 = mb_strtolower($faq->answer ?? '');

            // compute similarity via similar_text for question and answer
            $scoreQ = 0;
            $scoreA = 0;
            similar_text($lowerQ, $text1, $scoreQ);
            similar_text($lowerQ, $text2, $scoreA);

            $score = max($scoreQ, $scoreA);

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $faq;
            }
        }

        // Threshold: require at least some similarity (e.g. 25%). Otherwise return suggestions empty.
        if ($best && $bestScore >= 25) {
            return response()->json(['success' => true, 'data' => ['faq' => $best, 'score' => $bestScore, 'source' => 'db']]);
        }

        // No confident DB match — ask Gemini to generate possible FAQ pairs and return first result
        try {
            // We ask Gemini to return JSON array of {question,answer} (but model may return {answer} only)
            $raw = $this->gemini->faqFromTicket($q);
            $maybe = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($maybe) && count($maybe) > 0) {
                // If model returned an indexed array of objects (e.g. [{question,answer}, ...])
                if (isset($maybe[0]) && is_array($maybe[0])) {
                    $first = $maybe[0];
                    $generated = [
                        'question' => $first['question'] ?? null,
                        'answer' => $first['answer'] ?? null,
                    ];
                } elseif (isset($maybe['answer'])) {
                    // Model returned a single object with only 'answer'
                    $generated = [
                        'question' => null,
                        'answer' => $maybe['answer'] ?? null,
                    ];
                } else {
                    // Unexpected shape: try to stringify best-effort
                    $generated = [
                        'question' => null,
                        'answer' => is_string($maybe) ? $maybe : json_encode($maybe),
                    ];
                }

                // Persist generated answer into pending_faqs so admins can review it
                try {
                    PendingFaq::create([
                        'question' => $generated['question'] ?? $q,
                        'answer' => $generated['answer'] ?? '',
                        'language' => $validated['language'] ?? 'en',
                        'category' => null,
                        'user_id' => Auth::id() ?? null,
                        'status' => 'pending',
                        'raw_model_output' => is_string($raw) ? $raw : json_encode($raw),
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Failed to save pending FAQ from AI: ' . $e->getMessage());
                }

                return response()->json(['success' => true, 'data' => ['faq' => $generated, 'score' => $bestScore, 'source' => 'ai']]);
            }

            // If model didn't return JSON, return raw text as fallback
            return response()->json(['success' => true, 'data' => ['faq' => null, 'faq_text' => $raw, 'score' => $bestScore, 'source' => 'ai']]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Unable to generate fallback answer', 'error' => $e->getMessage()], 500);
        }
    }
}
