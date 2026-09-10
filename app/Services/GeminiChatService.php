<?php

namespace App\Services;

class GeminiChatService
{
    use ChatbotTrait;

    protected string $endpoint;

    public function __construct()
    {
        $endpoint = (string) config('services.gemini.endpoint', env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key='));
        $apiKey = (string) config('services.gemini.api_key', env('GEMINI_API_KEY', ''));
        $this->endpoint = str_contains($endpoint, 'key=') ? $endpoint . $apiKey : $endpoint . '?key=' . $apiKey;
    }

    public function ask(string $prompt): string
    {
        $context = $this->getContext();
        $systemPrompt = $this->getSystemPrompt($context);

        $response = $this->getHttpClient()->post($this->endpoint, [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $systemPrompt."\n\nPertanyaan pengguna:\n".$prompt],
                    ],
                ],
            ],
        ]);

        if ($response->successful()) {
            $data = $response->json();

            return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, saya tidak tahu jawabannya.';
        }

        return 'Terjadi kesalahan: '.$response->body();
    }
}
