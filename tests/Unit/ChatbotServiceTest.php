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
}
