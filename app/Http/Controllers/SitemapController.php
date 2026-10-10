<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

final class SitemapController extends Controller
{
    public function __invoke(SitemapService $sitemap): Response
    {
        return response()->view(
            'sitemap',
            ['entries' => $sitemap->entries()],
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8']
        );
    }
}
