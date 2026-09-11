<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class GroqChatService
{
    use ChatbotTrait;

    protected string $endpoint;

    /**
     * @var string[]
     */
    protected array $apiKeys = [];

    protected string $model;

    public function __construct()
    {
        $rawKeys = (string) config('services.groq.api_key', env('GROQ_API_KEY'));
        $this->apiKeys = $this->parseApiKeys($rawKeys);
        $this->endpoint = 'https://api.groq.com/openai/v1/chat/completions';
        $this->model = (string) config('services.groq.model', env('GROQ_MODEL', 'openai/gpt-oss-120b'));
    }

    public function ask(string $prompt): string
    {
        $context = $this->getContext();
        $systemPrompt = $this->getSystemPrompt($context);

        $keys = $this->getRotatedKeys($this->apiKeys, 'groq_chat_key_index');
        if (empty($keys)) {
            return $this->getFriendlyFallbackMessage();
        }

        $lastError = '';

        foreach ($keys as $apiKey) {
            try {
                $response = $this->getHttpClient([
                    'Authorization' => 'Bearer '.$apiKey,
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
                    'temperature' => 0.2,
                    'max_tokens' => 1024,
                ]);

                $rawBody = $response->body();
                Log::info('Groq Chat API raw response: '.$rawBody);

                if ($response->successful()) {
                    $data = $response->json();

                    return $data['choices'][0]['message']['content'] ?? 'Maaf, saya tidak tahu jawabannya.';
                }

                $lastError = $rawBody;
                Log::warning('Groq Chat API key failed: '.$lastError);
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                Log::error('Groq Chat API exception: '.$lastError);
            }
        }

        return 'Terjadi kesalahan: '.$lastError;
    }
}
