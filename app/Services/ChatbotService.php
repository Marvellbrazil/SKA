<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ChatbotService
{
    use ChatbotTrait;

    protected $gemini;

    protected $groq;

    public function __construct(
        GeminiChatService $gemini,
        GroqChatService $groq
    ) {
        $this->gemini = $gemini;
        $this->groq = $groq;
    }

    public function ask(string $prompt): string
    {
        $provider = strtoupper((string) config('services.chatbot.provider', env('CHATBOT_PROVIDER', 'GROQ')));

        try {
            $response = ($provider === 'GEMINI')
                ? $this->gemini->ask($prompt)
                : $this->groq->ask($prompt);

            if (str_contains($response, 'Terjadi kesalahan')) {
                Log::error("Chatbot {$provider} error: {$response}");

                return $this->getFriendlyFallbackMessage();
            }

            return $response;
        } catch (\Throwable $e) {
            Log::error("Chatbot {$provider} Exception: ".$e->getMessage());

            return $this->getFriendlyFallbackMessage();
        }
    }
}

