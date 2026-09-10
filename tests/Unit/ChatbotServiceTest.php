<?php

namespace Tests\Unit;

use App\Services\ChatbotService;
use App\Services\ChatbotTrait;
use App\Services\GeminiChatService;
use App\Services\GroqChatService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ChatbotServiceTest extends TestCase
{
    use ChatbotTrait;

    public function test_get_context_reads_summary_file_when_present(): void
    {
        $summaryFile = storage_path('app/data/summary_sekolah.txt');
        $this->assertTrue(File::exists($summaryFile));

        $context = $this->getContext();
        $this->assertStringContainsString('Profil SMK PGRI 3 Malang', $context);
    }

    public function test_chatbot_service_uses_groq_when_provider_is_groq(): void
    {
        config(['services.chatbot.provider' => 'GROQ']);

        $geminiMock = $this->createMock(GeminiChatService::class);
        $groqMock = $this->createMock(GroqChatService::class);

        $groqMock->expects($this->once())
            ->method('ask')
            ->with('Halo')
            ->willReturn('Jawaban dari Groq');

        $geminiMock->expects($this->never())
            ->method('ask');

        $service = new ChatbotService($geminiMock, $groqMock);
        $result = $service->ask('Halo');

        $this->assertEquals('Jawaban dari Groq', $result);
    }

    public function test_chatbot_service_uses_gemini_when_provider_is_gemini(): void
    {
        config(['services.chatbot.provider' => 'GEMINI']);

        $geminiMock = $this->createMock(GeminiChatService::class);
        $groqMock = $this->createMock(GroqChatService::class);

        $geminiMock->expects($this->once())
            ->method('ask')
            ->with('Halo')
            ->willReturn('Jawaban dari Gemini');

        $groqMock->expects($this->never())
            ->method('ask');

        $service = new ChatbotService($geminiMock, $groqMock);
        $result = $service->ask('Halo');

        $this->assertEquals('Jawaban dari Gemini', $result);
    }

    public function test_chatbot_service_returns_friendly_fallback_on_groq_error(): void
    {
        config(['services.chatbot.provider' => 'GROQ']);

        $geminiMock = $this->createMock(GeminiChatService::class);
        $groqMock = $this->createMock(GroqChatService::class);

        $groqMock->expects($this->once())
            ->method('ask')
            ->willReturn('Terjadi kesalahan: 429 Too Many Requests');

        $geminiMock->expects($this->never())
            ->method('ask');

        Log::shouldReceive('error')
            ->once();

        $service = new ChatbotService($geminiMock, $groqMock);
        $result = $service->ask('Halo');

        $this->assertStringContainsString('Maaf, asisten AI SKARIBOT saat ini sedang mengalami lonjakan antrean', $result);
    }

    public function test_chatbot_service_returns_friendly_fallback_on_exception(): void
    {
        config(['services.chatbot.provider' => 'GROQ']);

        $geminiMock = $this->createMock(GeminiChatService::class);
        $groqMock = $this->createMock(GroqChatService::class);

        $groqMock->expects($this->once())
            ->method('ask')
            ->willThrowException(new \Exception('Connection timeout'));

        $geminiMock->expects($this->never())
            ->method('ask');

        Log::shouldReceive('error')
            ->once();

        $service = new ChatbotService($geminiMock, $groqMock);
        $result = $service->ask('Halo');

        $this->assertStringContainsString('Maaf, asisten AI SKARIBOT saat ini sedang mengalami lonjakan antrean', $result);
    }

    public function test_parse_api_keys_handles_comma_separated_values(): void
    {
        $parsed = $this->parseApiKeys(' key1 , key2, "key3" , key1 ');
        $this->assertEquals(['key1', 'key2', 'key3'], $parsed);
    }

    public function test_get_rotated_keys_rotates_order(): void
    {
        $keys = ['keyA', 'keyB', 'keyC'];
        $first = $this->getRotatedKeys($keys, 'test_rotation_key');
        $second = $this->getRotatedKeys($keys, 'test_rotation_key');

        $this->assertCount(3, $first);
        $this->assertCount(3, $second);
        $this->assertNotEquals($first, $second);
    }

    public function test_groq_chat_service_failover_to_next_key_on_429(): void
    {
        config(['services.groq.api_key' => 'groq_key_1,groq_key_2']);

        $requestCount = 0;
        \Illuminate\Support\Facades\Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => function (\Illuminate\Http\Client\Request $request) use (&$requestCount) {
                $requestCount++;
                if ($request->hasHeader('Authorization', 'Bearer groq_key_1')) {
                    return \Illuminate\Support\Facades\Http::response(['error' => 'Rate limited'], 429);
                }

                return \Illuminate\Support\Facades\Http::response([
                    'choices' => [
                        ['message' => ['content' => 'Jawaban dari key 2']]
                    ]
                ], 200);
            },
        ]);

        \Illuminate\Support\Facades\Cache::put('groq_chat_key_index', 1);

        $groqService = new GroqChatService();
        $reply = $groqService->ask('Halo');

        $this->assertEquals('Jawaban dari key 2', $reply);
        $this->assertGreaterThanOrEqual(2, $requestCount);
    }
}
