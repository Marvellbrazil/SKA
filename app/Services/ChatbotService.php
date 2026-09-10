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
        $primaryProvider = strtoupper((string) config('services.chatbot.provider', env('CHATBOT_PROVIDER', 'GROQ')));

        if ($primaryProvider === 'GROQ') {
            try {
                $response = $this->groq->ask($prompt);

                if (
                    str_contains($response, 'Terjadi kesalahan') ||
                    str_contains($response, 'rate_limit') ||
                    str_contains($response, '429') ||
                    str_contains($response, 'rate_limit_exceeded')
                ) {
                    Log::warning('Groq Failed/Rate Limited. Falling back to Gemini.');
                    $geminiResponse = $this->gemini->ask($prompt);

                    if (str_contains($geminiResponse, 'Terjadi kesalahan')) {
                        Log::error('Gemini fallback failed: '.$geminiResponse);

                        return $this->getFriendlyFallbackMessage();
                    }

                    return $geminiResponse;
                }

                return $response;
            } catch (\Exception $e) {
                Log::error('Groq Exception: '.$e->getMessage());
                $geminiResponse = $this->gemini->ask($prompt);

                if (str_contains($geminiResponse, 'Terjadi kesalahan')) {
                    Log::error('Gemini fallback failed: '.$geminiResponse);

                    return $this->getFriendlyFallbackMessage();
                }

                return $geminiResponse;
            }
        }

        $geminiResponse = $this->gemini->ask($prompt);
        if (str_contains($geminiResponse, 'Terjadi kesalahan')) {
            Log::error('Gemini failed: '.$geminiResponse);

            return $this->getFriendlyFallbackMessage();
        }

        return $geminiResponse;
    }
}

