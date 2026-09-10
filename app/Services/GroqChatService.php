<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GroqChatService
{
    use ChatbotTrait;

    protected string $endpoint;

    protected string $apiKey;

    protected string $model;

    public function __construct()
    {
        $this->apiKey = (string) $this->sanitizeKey(config('services.groq.api_key', env('GROQ_API_KEY')));
        $this->endpoint = 'https://api.groq.com/openai/v1/chat/completions';
        $this->model = (string) config('services.groq.model', env('GROQ_MODEL', 'openai/gpt-oss-120b'));
    }

    public function ask(string $prompt): string
    {
        $context = $this->getContext();
        $systemPrompt = $this->getSystemPrompt($context);

        try {
            $response = $this->getHttpClient([
                'Authorization' => 'Bearer '.$this->apiKey,
            ])->post($this->endpoint, [
                'model' => $this->model,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1024,
            ]);

            $rawBody = $response->body();
            Log::info('Groq Chat API raw response: '.$rawBody);

            if ($response->successful()) {
                $data = $response->json();

                return $data['choices'][0]['message']['content'] ?? 'Maaf, saya tidak tahu jawabannya.';
            }

            return 'Terjadi kesalahan: '.$rawBody;
        } catch (\Throwable $e) {
            Log::error('Groq Chat API exception: '.$e->getMessage());

            return 'Terjadi kesalahan: '.$e->getMessage();
        }
    }
}
