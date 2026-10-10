<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Jurusan;
use Database\Seeders\ProfilSeeder;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_PATHS = ['/', '/berita', '/profil', '/prestasi', '/jurusan', '/ekstrakurikuler', '/alumni', '/pendaftaran'];

    public function test_pages_keep_the_original_styles_and_slider_runtime(): void
    {
        $this->seed(ProfilSeeder::class);

        foreach (self::PUBLIC_PATHS as $path) {
            $response = $this->get($path);

            $response->assertOk();
            $response->assertSee('https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', false);
            $response->assertSee('https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css', false);
            $response->assertSee('https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js', false);
            $response->assertSee('rel="canonical"', false);
            $response->assertSee('application/ld+json', false);
            $response->assertDontSee('build/assets/app.css"', false);
        }
    }

    public function test_major_cards_keep_the_original_modal_without_extra_visible_controls(): void
    {
        $jurusan = Jurusan::create([
            'jurusan' => 'Teknik Kendaraan Ringan',
            'departemen' => 'OTOMOTIF',
            'deskripsi' => 'Perawatan kendaraan ringan.',
        ]);

        $response = $this->get('/jurusan');

        $response->assertOk();
        $response->assertSee('handleJurusanClick(', false);
        $response->assertSee(route('jurusan.show', $jurusan->slug), false);
        $response->assertDontSee('Lihat detail');
    }

    public function test_every_local_asset_referenced_by_public_pages_exists(): void
    {
        $this->seed(ProfilSeeder::class);
        $missing = [];

        foreach (self::PUBLIC_PATHS as $path) {
            $html = $this->get($path)->getContent();
            preg_match_all('#(?:src|href)="http://localhost/(assets/[^"?\#]+)"|url\(\'http://localhost/(assets/[^\')]+)\'\)#', $html, $matches);

            foreach (array_filter(array_merge($matches[1], $matches[2])) as $asset) {
                if (! is_file(public_path(rawurldecode($asset)))) {
                    $missing[] = "{$path} -> {$asset}";
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }

    public function test_homepage_keeps_original_hero_and_preloads_the_same_image(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('rel="preload" as="image" href="http://localhost/assets/Hero1.jpeg"', false);
        $response->assertSee("background-image: url('http://localhost/assets/Hero1.jpeg')", false);
        $response->assertSee('assets/Depart1.png', false);
        $response->assertDontSee('assets/Hero1.webp', false);
    }

    public function test_pages_load_the_original_font_families(): void
    {
        $this->seed(ProfilSeeder::class);

        foreach (self::PUBLIC_PATHS as $path) {
            $response = $this->get($path);

            $response->assertSee('family=Poppins:wght@100;200;300;400;500;600;700;800;900', false);
            $response->assertSee('family=Bebas+Neue&family=Poppins:wght@400;500;600;700', false);
        }
    }

    public function test_every_icon_class_used_in_views_exists_in_the_original_icon_stylesheet(): void
    {
        $stylesheet = file_get_contents(base_path('node_modules/@fortawesome/fontawesome-free/css/all.css'));
        $modifiers = ['fa-solid', 'fa-regular', 'fa-brands', 'fa-classic', 'fa-fw', 'fa-spin', 'fa-lg', 'fa-xs', 'fa-sm', 'fa-xl', 'fa-2xl'];
        $missing = [];

        foreach (['resources/views', 'resources/ts', 'resources/js', 'app'] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($directory), FilesystemIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if (preg_match('/\.(php|js|ts)$/', $file->getFilename()) !== 1) {
                    continue;
                }

                preg_match_all('/\bfa-[a-z0-9-]+/', (string) file_get_contents($file->getPathname()), $matches);

                foreach ($matches[0] as $token) {
                    if (! in_array($token, $modifiers, true) && preg_match('/\\.'.preg_quote($token, '/').'(?=[\\s,{])/', $stylesheet) !== 1) {
                        $missing[$token] = true;
                    }
                }
            }
        }

        $this->assertSame([], array_keys($missing));
    }

    public function test_marquee_query_only_runs_for_the_page_that_renders_it(): void
    {
        $this->seed(ProfilSeeder::class);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->get('/jurusan')->assertOk();
        $this->assertCount(0, array_filter($queries, fn (string $sql): bool => str_contains($sql, 'marquees')));

        $queries = [];
        $this->get('/profil')->assertOk();
        $this->assertCount(1, array_filter($queries, fn (string $sql): bool => str_contains($sql, 'marquees')));
    }
}
