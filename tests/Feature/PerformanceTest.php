<?php

declare(strict_types=1);

namespace Tests\Feature;

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

    public function test_pages_do_not_load_render_blocking_cdn_assets(): void
    {
        $this->seed(ProfilSeeder::class);

        foreach (self::PUBLIC_PATHS as $path) {
            $html = $this->get($path)->getContent();

            $this->assertStringNotContainsString('cdn.jsdelivr.net', $html, $path);
            $this->assertStringNotContainsString('build/assets/app.css"', $html, $path);
            $this->assertLessThanOrEqual(1, preg_match_all('#<link[^>]+rel="stylesheet"[^>]+build/assets/app-#', $html), "{$path} must load the app stylesheet once");
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

    public function test_pages_do_not_depend_on_third_party_font_hosts(): void
    {
        $this->seed(ProfilSeeder::class);

        foreach (self::PUBLIC_PATHS as $path) {
            $html = $this->get($path)->getContent();

            $this->assertStringNotContainsString('fonts.googleapis.com', $html, $path);
            $this->assertStringNotContainsString('fonts.gstatic.com', $html, $path);
        }
    }

    public function test_every_icon_class_used_in_views_exists_in_the_generated_icon_stylesheet(): void
    {
        $stylesheet = file_get_contents(resource_path('css/icons.css'));
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
                    if (! in_array($token, $modifiers, true) && ! str_contains($stylesheet, '.'.$token.'{')) {
                        $missing[$token] = true;
                    }
                }
            }
        }

        $this->assertSame([], array_keys($missing), 'run php scripts/build-icons.php');
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
