<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecommendationControllerTest extends TestCase
{
    public function test_recommendation_requires_keyword(): void
    {
        $response = $this->postJson('/api/gemini', []);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Keyword wajib diisi']);
    }

    public function test_recommendation_groq_success(): void
    {
        Config::set('services.groq.api_key', 'mock_groq_key');

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'jurusan_utama' => [
                                    'name' => 'RPL',
                                    'department' => 'TIK',
                                    'description' => 'Rekayasa Perangkat Lunak'
                                ],
                                'jurusan_alternatif' => [
                                    'name' => 'TKJ',
                                    'department' => 'TIK',
                                    'description' => 'Teknik Komputer Jaringan'
                                ]
                            ])
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->postJson('/api/gemini', ['keyword' => 'coding dan software']);

        $response->assertStatus(200)
            ->assertJsonPath('jurusan_utama.name', 'RPL')
            ->assertJsonPath('jurusan_alternatif.name', 'TKJ');
    }

    public function test_recommendation_fallback_to_gemini_when_groq_fails(): void
    {
        Config::set('services.groq.api_key', 'mock_groq_key');
        Config::set('services.gemini.api_key', 'mock_gemini_key');
        Config::set('services.gemini.endpoint', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=');

        Http::fake([
            'https://api.groq.com/openai/v1/chat/completions' => Http::response(['error' => 'Rate limit'], 429),
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'jurusan_utama' => [
                                            'name' => 'DKV',
                                            'department' => 'TIK',
                                            'description' => 'Desain Komunikasi Visual'
                                        ],
                                        'jurusan_alternatif' => [
                                            'name' => 'NIMA',
                                            'department' => 'TIK',
                                            'description' => 'Animasi'
                                        ]
                                    ])
                                ]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->postJson('/api/gemini', ['keyword' => 'gambar dan animasi']);

        $response->assertStatus(200)
            ->assertJsonPath('jurusan_utama.name', 'DKV')
            ->assertJsonPath('jurusan_alternatif.name', 'NIMA');
    }

    public function test_recommendation_returns_500_when_both_fail(): void
    {
        Config::set('services.groq.api_key', 'mock_groq_key');
        Config::set('services.gemini.api_key', 'mock_gemini_key');

        Http::fake([
            'https://api.groq.com/*' => Http::response(['error' => 'Failed'], 500),
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => 'Failed'], 500),
        ]);

        $response = $this->postJson('/api/gemini', ['keyword' => 'mesin motor']);

        $response->assertStatus(500)
            ->assertJson(['error' => 'Gagal menghubungi AI service']);
    }
}
