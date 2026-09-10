<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GeminiChatService
{
    use ChatbotTrait;

    protected string $endpoint;

    /**
     * @var string[]
     */
    protected array $apiKeys = [];

    public function __construct()
    {
        $this->endpoint = (string) config('services.gemini.endpoint', env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key='));

        $keys = [
            $this->sanitizeKey(config('services.gemini.api_key', env('GEMINI_API_KEY'))),
            $this->sanitizeKey(config('services.gemini.backup_key', env('GEMINI_BACKUP_API_KEY'))),
            'AIzaSyDsyQUaUf6zE7py7ppxqIIx41a_4npFoSw',
        ];

        $this->apiKeys = array_values(array_unique(array_filter($keys)));
    }

    public function ask(string $prompt): string
    {
        $context = $this->getContext();
        $systemPrompt = $this->getSystemPrompt($context);

        if (empty($this->apiKeys)) {
            return $this->getFriendlyFallbackMessage();
        }

        $lastError = '';

        foreach ($this->apiKeys as $apiKey) {
            $url = str_contains($this->endpoint, 'key=')
                ? $this->endpoint . $apiKey
                : $this->endpoint . '?key=' . $apiKey;

            try {
                $response = $this->getHttpClient()->post($url, [
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
                    $rawBody = $response->body();
                    Log::info('Gemini Chat API raw response: '.$rawBody);
                    $data = $response->json();

                    return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Maaf, saya tidak tahu jawabannya.';
                }

                $lastError = $response->body();
                Log::warning('Gemini Chat API key failed: '.$lastError);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::error('Gemini Chat API exception: '.$lastError);
            }
        }

        return 'Terjadi kesalahan: '.$lastError;
    }
}
