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
        $this->model  = config('services.gemini.model', env('GEMINI_MODEL', 'gemini-2.5-flash'));

        if (empty($this->apiKey)) {
            Log::warning('GeminiService constructed without GEMINI_API_KEY');
        }
    }

    protected function buildUrl(): array
    {
        $base = "{$this->apiUrl}/models/{$this->model}:generateContent";
        if (!empty($this->apiKey) && str_starts_with($this->apiKey, 'AIza')) {
            return ['url' => $base . '?key=' . urlencode($this->apiKey), 'use_bearer' => false];
        }
        return ['url' => $base, 'use_bearer' => true];
    }

    protected function callGenerate(string $text): string
    {
        $payload = [
            'contents' => [
                ['parts' => [['text' => $text]]]
            ]
        ];

        $build = $this->buildUrl();
        $url = $build['url'];

        try {
            $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
            if ($build['use_bearer']) {
                if (empty($this->apiKey)) {
                    throw new RuntimeException('Gemini request failed: missing OAuth token.');
                }
                $headers['Authorization'] = "Bearer {$this->apiKey}";
            }

            $response = Http::withHeaders($headers)
                ->timeout(30)
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

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function suggestReply(string $message): string
    {
        $prompt = "You are a helpful assistant. Suggest a short, professional reply to:\n\n{$message}";
        return $this->callGenerate($prompt);
    }

    public function translate(string $text, string $to): string
    {
        $prompt = "Translate the following text to {$to}:\n\n{$text}";
        return $this->callGenerate($prompt);
    }

    public function faqFromTicket(string $ticketText): string
    {
        $prompt = "Extract 3 short FAQ Q&A pairs from the following ticket:\n\n{$ticketText}";
        return $this->callGenerate($prompt);
    }
}
