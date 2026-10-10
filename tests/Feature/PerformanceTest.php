<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\ProfilSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_PATHS = ['/', '/berita', '/profil', '/prestasi', '/jurusan', '/ekstrakurikuler', '/alumni', '/pendaftaran'];

    public function test_pages_do_not_load_render_blocking_cdn_assets(): void
    {
        $this->seed(ProfilSeeder::class);

        foreach (self::PUBLIC_PATHS as $path) {
            $html = $this->get($path)->getContent();

            $this->assertStringNotContainsString('cdn.jsdelivr.net', $html, $path);
            $this->assertStringNotContainsString('build/assets/app.css"', $html, $path);
            $this->assertSame(1, substr_count($html, 'fonts.googleapis.com/css2'), "{$path} must request Google Fonts once");
        }
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

    public function test_large_raster_assets_are_not_referenced_by_public_pages(): void
    {
        $this->seed(ProfilSeeder::class);
        $heavy = [];

        foreach (self::PUBLIC_PATHS as $path) {
            preg_match_all('#http://localhost/(assets/[^"\')?\#]+\.(?:png|jpe?g))#i', $this->get($path)->getContent(), $matches);

            foreach ($matches[1] as $asset) {
                $file = public_path(rawurldecode($asset));

                if (is_file($file) && filesize($file) > 150 * 1024) {
                    $heavy[] = "{$path} -> {$asset} (".intdiv(filesize($file), 1024).' KB)';
                }
            }
        }

        $this->assertSame([], array_values(array_unique($heavy)));
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
