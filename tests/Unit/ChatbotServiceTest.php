<?php

namespace Tests\Unit;

use App\Models\Profil;
use App\Services\ChatbotService;
use App\Services\ChatbotTrait;
use App\Services\GeminiChatService;
use App\Services\GroqChatService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => function (Request $request) use (&$requestCount) {
                $requestCount++;
                if ($request->hasHeader('Authorization', 'Bearer groq_key_1')) {
                    return Http::response(['error' => 'Rate limited'], 429);
                }

                return Http::response([
                    'choices' => [
                        ['message' => ['content' => 'Jawaban dari key 2']],
                    ],
                ], 200);
            },
        ]);

        Cache::put('groq_chat_key_index', 1);

        $groqService = new GroqChatService;
        $reply = $groqService->ask('Halo');

        $this->assertEquals('Jawaban dari key 2', $reply);
        $this->assertGreaterThanOrEqual(2, $requestCount);
    }

    public function test_system_prompt_strictly_restricts_scope_and_refuses_general_coding(): void
    {
        $prompt = $this->getSystemPrompt('Dummy context');

        $this->assertStringContainsString('DILARANG KERAS menjawab pertanyaan di luar informasi SMK PGRI 3 Malang', $prompt);
        $this->assertStringContainsString('pembuatan script/kode pemrograman', $prompt);
        $this->assertStringContainsString('DILARANG mengait-ngaitkan pertanyaan umum', $prompt);
        $this->assertStringContainsString('WAJIB MENOLAK', $prompt);
    }

    public function test_system_prompt_contains_pendaftaran_rules_and_reasoning_restrictions(): void
    {
        $prompt = $this->getSystemPrompt('Dummy context');

        $this->assertStringContainsString('Gelombang 1 SELALU dibuka pada tanggal 10 bulan 10', $prompt);
        $this->assertStringContainsString('JANGAN PERNAH menampilkan langkah, proses, atau teks penalaran/reasoning', $prompt);
        $this->assertStringContainsString('Kartu Keluarga (KK)', $prompt);
        $this->assertStringContainsString('MERAH atau BIRU', $prompt);
        $this->assertStringContainsString('DILARANG menyebutkan atau menampilkan \'Catatan penting: Tidak diperlukan surat keterangan sehat\'', $prompt);
    }

    public function test_system_prompt_contains_fasilitas_and_kelebihan_rules(): void
    {
        $prompt = $this->getSystemPrompt('Dummy context');

        $this->assertStringContainsString('Ruang kelas', $prompt);
        $this->assertStringContainsString('Laboratorium dan bengkel praktik', $prompt);
        $this->assertStringContainsString('Lapangan basket dan futsal', $prompt);
        $this->assertStringContainsString('Tablet gratis saat pendaftaran', $prompt);
        $this->assertStringContainsString('Badan Kejasama Industri', $prompt);

        $this->assertStringContainsString('Prioritas Pertama: Prestasi Internasional', $prompt);
        $this->assertStringContainsString('Prioritas Kedua: Prestasi Nasional', $prompt);
        $this->assertStringContainsString('Prioritas Ketiga: Prestasi Regional', $prompt);
        $this->assertStringContainsString('DILARANG KERAS menampilkan poin \'Lokasi Strategis\'', $prompt);
    }

    public function test_format_chat_response_cleans_markdown_and_escapes(): void
    {
        $input = "### Judul Bagian\nBerikut contoh **teks tebal** dan *teks miring*:\n- Poin 1\n* Poin 2\nAda escape \\*bintang\\* dan \\[tanda kurung\\].";
        $formatted = $this->formatChatResponse($input);

        $this->assertStringContainsString('<b>Judul Bagian</b>', $formatted);
        $this->assertStringContainsString('<b>teks tebal</b>', $formatted);
        $this->assertStringContainsString('• Poin 1', $formatted);
        $this->assertStringContainsString('• Poin 2', $formatted);
        $this->assertStringNotContainsString('**', $formatted);
        $this->assertStringNotContainsString('\*', $formatted);
        $this->assertStringNotContainsString('\[', $formatted);
    }

    public function test_format_chat_response_strips_reasoning_and_think_tags(): void
    {
        $input = "<think>Ini adalah penalaran internal model AI mengenai tanggal penutupan pendaftaran.</think>Reasoning: menganalisis database...\n\n<b>1. Jadwal Pendaftaran</b>\nPendaftaran Gelombang 1 dibuka 10 Oktober.";
        $formatted = $this->formatChatResponse($input);

        $this->assertStringNotContainsString('<think>', $formatted);
        $this->assertStringNotContainsString('penalaran internal', $formatted);
        $this->assertStringNotContainsString('Reasoning:', $formatted);
        $this->assertStringContainsString('<b>1. Jadwal Pendaftaran</b>', $formatted);
    }

    public function test_format_chat_response_removes_surat_sehat_when_not_explicitly_asked(): void
    {
        $input = "2. Dokumen Persyaratan\n• Fotokopi Kartu Keluarga (KK)\n• Fotokopi KTP orang tua / wali\n• Ijazah atau Surat Keterangan Lulus (SKL) SMP/MTs\n• Pas foto ukuran 3×4 cm, berwarna dengan latar belakang MERAH atau BIRU\n• Catatan penting: Tidak diperlukan surat keterangan sehat.";
        $formatted = $this->formatChatResponse($input, 'Bagaimana cara mendaftar?');

        $this->assertStringNotContainsString('surat keterangan sehat', $formatted);
        $this->assertStringContainsString('Fotokopi Kartu Keluarga (KK)', $formatted);
    }

    public function test_format_chat_response_retains_surat_sehat_when_explicitly_asked(): void
    {
        $input = "Untuk pendaftaran di SMK PGRI 3 Malang, tidak diperlukan surat keterangan sehat.";
        $formatted = $this->formatChatResponse($input, 'Apakah perlu membawa surat keterangan sehat?');

        $this->assertStringContainsString('tidak diperlukan surat keterangan sehat', $formatted);
    }

    public function test_format_chat_response_removes_lokasi_strategis_on_kelebihan_prompt(): void
    {
        $input = "1. Program Industri Unggulan\n• Kelas industri Toyota dsb.\n\n7. Lokasi Strategis\n• Terletak di Jl. Raya Tlogomas Gg. 9 No. 29, Lowokwaru, Malang, mudah diakses dan dekat dengan fasilitas umum. Lokasi Google Maps\n\n2. Fasilitas Lengkap\n• Ruang kelas dan lab praktik.";
        $formatted = $this->formatChatResponse($input, 'kelebihan smk pgri 3 malang dengan sekolah lain?');

        $this->assertStringNotContainsString('Lokasi Strategis', $formatted);
        $this->assertStringContainsString('Program Industri Unggulan', $formatted);
        $this->assertStringContainsString('Fasilitas Lengkap', $formatted);
    }

    public function test_get_database_context_extracts_school_profile_when_database_is_populated(): void
    {
        $migration = require database_path('migrations/2025_10_16_034545_create_profils_table.php');
        $migration->up();

        Profil::create([
            'heroTitle' => 'SKARIGA',
            'profilDesc' => 'Sejarah SKARIGA sejak 1986',
            'visiImageName' => 'Moch. Lukman Hakim, S.T, M.M.',
            'visiDesc' => 'Menjadi SMK Unggul',
            'youtubeSrc' => 'https://youtube.com',
        ]);

        Cache::forget('chatbot_school_context_v4');
        $context = $this->getDatabaseContext();

        $this->assertNotNull($context);
        $this->assertStringContainsString('Kepala Sekolah: Moch. Lukman Hakim, S.T, M.M.', $context);
        $this->assertStringContainsString('Visi: Menjadi SMK Unggul', $context);
        $this->assertStringContainsString('Sejarah SKARIGA sejak 1986', $context);
        $this->assertStringContainsString('Gelombang 1 SELALU dibuka pada tanggal 10 bulan 10', $context);
        $this->assertStringContainsString('MERAH atau BIRU', $context);
        $this->assertStringContainsString('Fasilitas yang ada di SMK PGRI 3 Malang antara lain:', $context);
        $this->assertStringContainsString('Prestasi Tingkat Internasional (Prioritas Pertama', $context);
    }

    public function test_get_context_falls_back_to_file_when_db_is_empty(): void
    {
        Cache::forget('chatbot_school_context');
        if (Schema::hasTable('profils')) {
            Profil::query()->delete();
        }

        $context = $this->getContext();
        $this->assertStringContainsString('Profil SMK PGRI 3 Malang', $context);
    }
}
