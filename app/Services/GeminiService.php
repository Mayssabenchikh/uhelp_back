<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GeminiService
{
    protected string $apiKey;
    protected string $apiUrl;
    protected string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', env('GEMINI_API_KEY', ''));
        $this->apiUrl = rtrim(config('services.gemini.url', env('GEMINI_API_URL', 'https://generativelanguage.googleapis.com/v1beta')), '/');

        // Accept either "models/gemini-1.5-pro" or "gemini-1.5-pro" in .env
        $rawModel = trim((string) config('services.gemini.model', env('GEMINI_MODEL', 'models/gemini-1.5-pro')));
        $this->model = $this->normalizeModel($rawModel);

        if (empty($this->apiKey)) {
            Log::warning('GeminiService constructed without GEMINI_API_KEY');
        }
    }

    protected function normalizeModel(string $m): string
    {
        $m = trim($m);
        if ($m === '') return 'models/gemini-1.5-pro';
        // If user passed full "models/..." keep it; otherwise prefix
        if (str_starts_with($m, 'models/')) {
            return $m;
        }
        return 'models/' . $m;
    }

    /**
     * Build request URL and indicate whether to pass API key as query (simple API key)
     * or use Bearer Authorization.
     *
     * @return array{url:string, use_bearer:bool}
     */
    protected function buildUrl(): array
    {
        $base = "{$this->apiUrl}/{$this->model}:generateContent";

        // If API key looks like Google API key starting with "AIza", use ?key=
        if (!empty($this->apiKey) && str_starts_with($this->apiKey, 'AIza')) {
            return ['url' => $base . '?key=' . urlencode($this->apiKey), 'use_bearer' => false];
        }

        return ['url' => $base, 'use_bearer' => true];
    }

    /**
     * Low-level call to the generate endpoint. Returns the main text or JSON debug dump.
     */
    protected function callGenerate(string $text, int $timeout = 30): string
    {
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ]
        ];

        $build = $this->buildUrl();
        $url = $build['url'];

        try {
            $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];

            if ($build['use_bearer']) {
                if (empty($this->apiKey)) {
                    throw new RuntimeException('Gemini request failed: missing OAuth/Bearer token.');
                }
                $headers['Authorization'] = "Bearer {$this->apiKey}";
            }

            $response = Http::withHeaders($headers)
                ->timeout($timeout)
                ->retry(2, 200)
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('Gemini HTTP request error', ['url' => $url, 'error' => $e->getMessage()]);
            throw new RuntimeException('Gemini request failed: ' . $e->getMessage());
        }

        if ($response->failed()) {
            Log::error('Gemini API returned failure', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Gemini request failed: ' . $response->status() . ' — ' . $response->body());
        }

        $data = $response->json();

        // Common shapes: candidates[0].content.parts[0].text
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }

        // Some variants: output or text
        if (isset($data['output']) && is_string($data['output'])) {
            return $data['output'];
        }
        if (isset($data['text']) && is_string($data['text'])) {
            return $data['text'];
        }

        // Fallback: gather textual values from response
        $textCandidates = $this->extractStrings($data);
        if (!empty($textCandidates)) {
            return implode("\n\n", array_slice($textCandidates, 0, 5));
        }

        // Final fallback: return raw JSON for debugging
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** Recursively extract small text strings from API response */
    protected function extractStrings($node): array
    {
        $result = [];
        if (is_string($node)) {
            $trim = trim($node);
            if ($trim !== '') $result[] = $trim;
            return $result;
        }
        if (is_array($node)) {
            foreach ($node as $v) {
                $result = array_merge($result, $this->extractStrings($v));
            }
        }
        return $result;
    }

    /* Public helpers used by controller */

    public function suggestReply(string $message): string
    {
        $prompt = "You are a helpful assistant. Suggest a short, professional reply to the following message. Keep it concise (one short paragraph):\n\n{$message}";
        return $this->callGenerate($prompt);
    }

    public function translate(string $text, string $to): string
    {
        $prompt = "Translate the following text to {$to} (preserve meaning and formatting):\n\n{$text}";
        return $this->callGenerate($prompt);
    }

    public function faqFromTicket(string $ticketText): string
    {
        // Ask for JSON structured output to facilitate frontend parsing
        $prompt = "Extract 3 short FAQ Q&A pairs from the following ticket. Return valid JSON array of objects with keys exactly: question and answer.\n\nTicket:\n{$ticketText}\n\nExample output:\n[{\"question\":\"...\",\"answer\":\"...\"}, ...]";
        return $this->callGenerate($prompt);
    }
}
