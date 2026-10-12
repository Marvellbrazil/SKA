<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Berita;
use App\Models\Jurusan;
use Database\Seeders\ProfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function makeBerita(string $title = 'Juara Nasional LKS'): Berita
    {
        return Berita::create([
            'title' => $title,
            'deskripsi' => 'Siswa SMK PGRI 3 Malang meraih juara.',
            'content' => '<p>Isi berita</p>',
            'type' => 'PRESTASI',
        ]);
    }

    private function makeJurusan(string $name = 'Teknik Kendaraan Ringan'): Jurusan
    {
        return Jurusan::create([
            'jurusan' => $name,
            'departemen' => 'OTOMOTIF',
            'deskripsi' => 'Mempelajari perawatan dan perbaikan kendaraan ringan.',
            'gambar' => 'jurusan/tkr.jpg',
        ]);
    }

    public function test_sitemap_compiles_with_short_open_tags_enabled(): void
    {
        $process = new Process([PHP_BINARY, '-d', 'short_open_tag=1', '-r', <<<'PHP'
require 'vendor/autoload.php';
$compiler = new Illuminate\View\Compilers\BladeCompiler(new Illuminate\Filesystem\Filesystem(), sys_get_temp_dir());
$compiled = $compiler->compileString(file_get_contents('resources/views/sitemap.blade.php'));
token_get_all($compiled, TOKEN_PARSE);
PHP], base_path());
        $process->run();

        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    public function test_unknown_url_returns_404_and_noindex(): void
    {
        $response = $this->get('/halaman-ngawur-xyz');

        $response->assertStatus(404);
        $response->assertSee('noindex, nofollow', false);
        $response->assertDontSee('rel="canonical"', false);
    }

    public function test_homepage_has_canonical_jsonld_lang_and_single_h1(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<html lang="id">', false);
        $response->assertSee('<link rel="canonical" href="http://localhost/">', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('"@type":"HighSchool"', false);
        $response->assertSee('index, follow', false);
        $this->assertSame(1, substr_count(strtolower($response->getContent()), '<h1'));
    }

    public function test_public_pages_have_unique_titles_and_descriptions(): void
    {
        $this->seed(ProfilSeeder::class);
        $descriptions = [];

        foreach (['/', '/berita', '/profil', '/prestasi', '/jurusan', '/ekstrakurikuler', '/alumni', '/pendaftaran'] as $path) {
            $response = $this->get($path);
            $this->assertSame(200, $response->getStatusCode(), "{$path} must return 200");
            $html = $response->getContent();

            preg_match('/<meta name="description" content="([^"]*)"/', $html, $match);
            $descriptions[] = $match[1] ?? '';
            $this->assertSame(1, substr_count(strtolower($html), '<h1'), "{$path} must have exactly one h1");
            $this->assertStringContainsString('rel="canonical"', $html, $path);
        }

        $this->assertCount(count($descriptions), array_unique($descriptions));
        $this->assertNotContains('', $descriptions);
    }

    public function test_robots_txt_declares_sitemap_and_blocks_private_paths(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://smkpgri3-malang.sch.id/sitemap.xml', $robots);
        $this->assertStringContainsString('Disallow: /admin', $robots);
    }

    public function test_footer_links_to_the_canonical_registration_page(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('href="/pendaftaran"', false)
            ->assertDontSee('href="/Pendaftaran"', false);
    }

    public function test_legacy_registration_url_redirects_permanently(): void
    {
        $this->get('/Pendaftaran')
            ->assertStatus(301)
            ->assertRedirect('/pendaftaran');

        $this->get('/pendaftaran')->assertOk();
    }

    public function test_indexed_chat_endpoint_can_be_crawled_for_removal(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Allow: /chat/ask$', $robots);
        $this->assertStringContainsString('Disallow: /chat/', $robots);

        $this->get('/chat/ask')
            ->assertNotFound()
            ->assertSee('noindex, nofollow', false);
    }

    public function test_sitemap_is_valid_xml_with_public_urls(): void
    {
        $berita = $this->makeBerita();
        $jurusan = $this->makeJurusan();
        Cache::flush();

        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml);

        $locations = array_map('strval', iterator_to_array($xml->url->loc ?? [], false));
        $all = [];
        foreach ($xml->url as $url) {
            $all[] = (string) $url->loc;
        }

        $this->assertContains('http://localhost/berita/'.$berita->slug, $all);
        $this->assertContains('http://localhost/jurusan/'.$jurusan->slug, $all);
        $this->assertContains('http://localhost/pendaftaran', $all);
        $this->assertNotEmpty($locations);
    }

    public function test_berita_detail_uses_slug_and_exposes_news_article_schema(): void
    {
        $berita = $this->makeBerita();

        $response = $this->get('/berita/'.$berita->slug);

        $response->assertOk();
        $response->assertSee('"@type":"NewsArticle"', false);
        $response->assertSee('"@type":"BreadcrumbList"', false);
        $response->assertSee('<link rel="canonical" href="http://localhost/berita/'.$berita->slug.'">', false);
        $response->assertSee('og:type" content="article"', false);
    }

    public function test_legacy_numeric_berita_url_redirects_permanently_to_slug(): void
    {
        $berita = $this->makeBerita();

        $this->get('/berita/'.$berita->id)->assertStatus(301)->assertRedirect('/berita/'.$berita->slug);
    }

    public function test_unknown_berita_returns_404(): void
    {
        $this->get('/berita/tidak-ada')->assertNotFound();
        $this->get('/berita/999999')->assertNotFound();
    }

    public function test_viewing_berita_increments_views_without_touching_updated_at(): void
    {
        $berita = $this->makeBerita();
        $updatedAt = $berita->fresh()->updated_at;

        $this->travel(2)->days();
        $this->get('/berita/'.$berita->slug)->assertOk();

        $fresh = $berita->fresh();
        $this->assertSame(1, $fresh->views);
        $this->assertTrue($updatedAt->equalTo($fresh->updated_at));
    }

    public function test_jurusan_gets_unique_slug_and_detail_page(): void
    {
        $first = $this->makeJurusan();
        $second = $this->makeJurusan();

        $this->assertSame('teknik-kendaraan-ringan', $first->slug);
        $this->assertSame('teknik-kendaraan-ringan-1', $second->slug);

        $response = $this->get('/jurusan/'.$first->slug);

        $response->assertOk();
        $response->assertSee('<h1', false);
        $response->assertSee('"@type":"EducationalOccupationalProgram"', false);
        $response->assertSee('Teknik Kendaraan Ringan', false);
    }

    public function test_slug_stays_unique_after_title_change(): void
    {
        $first = $this->makeBerita('Berita A');
        $second = $this->makeBerita('Berita B');

        $second->update(['title' => 'Berita A']);

        $this->assertNotSame($first->fresh()->slug, $second->fresh()->slug);
    }
}
