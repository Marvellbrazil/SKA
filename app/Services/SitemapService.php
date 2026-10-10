<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Berita;
use App\Models\Jurusan;
use App\Services\Seo\SeoService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

final class SitemapService
{
    private const CACHE_KEY = 'seo.sitemap.entries';

    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(private readonly SeoService $seo) {}

    public function entries(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn (): array => $this->build());
    }

    private function build(): array
    {
        $latestBerita = Berita::query()->latest('updated_at')->value('updated_at');

        $pages = [
            'landing.index' => $latestBerita,
            'berita.index' => $latestBerita,
            'profil.index' => null,
            'prestasi.index' => null,
            'jurusan.index' => null,
            'ekskul.index' => null,
            'alumni.index' => null,
            'pendaftaran.index' => null,
        ];

        $entries = [];

        foreach ($pages as $routeName => $lastModified) {
            $entries[] = $this->entry(route($routeName, [], false), $lastModified);
        }

        foreach (Berita::query()->latest('updated_at')->get(['slug', 'updated_at']) as $berita) {
            $entries[] = $this->entry(route('berita.show', ['berita' => $berita->slug], false), $berita->updated_at);
        }

        foreach (Jurusan::query()->whereNotNull('slug')->orderBy('jurusan')->get(['slug', 'updated_at']) as $jurusan) {
            $entries[] = $this->entry(route('jurusan.show', ['jurusan' => $jurusan->slug], false), $jurusan->updated_at);
        }

        return $entries;
    }

    private function entry(string $path, mixed $lastModified): array
    {
        return [
            'loc' => $this->seo->url($path),
            'lastmod' => $lastModified instanceof CarbonInterface ? $lastModified->toAtomString() : null,
        ];
    }
}
