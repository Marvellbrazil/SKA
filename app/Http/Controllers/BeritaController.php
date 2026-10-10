<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Berita;
use App\Services\Seo\SeoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BeritaController extends Controller
{
    public function index(Request $request): View
    {
        $type = $request->input('type', 'all');
        $query = Berita::query();

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $beritas = $query->latest()->paginate(9)->withQueryString();

        return view('berita', compact('beritas', 'type'));
    }

    public function show(string $berita, SeoService $seo): RedirectResponse|View
    {
        $record = Berita::query()->where('slug', $berita)->first();

        if ($record === null) {
            abort_unless(ctype_digit($berita), 404);

            $legacy = Berita::query()->findOrFail((int) $berita);

            return redirect()->route('berita.show', ['berita' => $legacy->slug], 301);
        }

        // Increment views
        Berita::withoutTimestamps(fn () => $record->incrementQuietly('views'));

        $beritaTerbaru = Berita::where('id', '!=', $record->id)
            ->latest()
            ->take(5)
            ->get();

        return view('detail-berita', [
            'berita' => $record,
            'beritaTerbaru' => $beritaTerbaru,
            'image' => $seo->storageUrl($record->gambar),
            'schema' => [
                $seo->breadcrumbs([['Beranda', '/'], ['Berita', 'berita'], [$record->title, 'berita/'.$record->slug]]),
                $seo->newsArticle($record),
            ],
        ]);
    }
}
