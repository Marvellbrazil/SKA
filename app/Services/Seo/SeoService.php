<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Models\Berita;
use App\Models\Jurusan;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class SeoService
{
    private const DESCRIPTION_LIMIT = 160;

    private const HEADLINE_LIMIT = 110;

    public function url(string $path = ''): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }

    public function asset(?string $pathOrUrl): string
    {
        $value = $pathOrUrl !== null && $pathOrUrl !== '' ? $pathOrUrl : (string) config('seo.image');

        return preg_match('#^https?://#i', $value) === 1 ? $value : $this->url($value);
    }

    public function storageUrl(?string $path): string
    {
        return $path !== null && $path !== '' ? $this->url('storage/'.$path) : $this->asset(null);
    }

    public function canonical(Request $request): string
    {
        $url = $this->url($request->path());
        $page = $request->integer('page', 1);

        return $page > 1 ? $url.'?page='.$page : $url;
    }

    public function description(?string $value): string
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $value)));

        return $clean === ''
            ? (string) config('seo.description')
            : Str::limit($clean, self::DESCRIPTION_LIMIT, '…');
    }

    public function organization(): array
    {
        $organization = config('seo.organization');
        $address = $organization['address'];

        return [
            '@type' => 'HighSchool',
            '@id' => $this->organizationId(),
            'name' => $organization['name'],
            'alternateName' => $organization['alternate_name'],
            'url' => $this->url(),
            'logo' => $this->asset($organization['logo']),
            'image' => $this->asset(null),
            'foundingDate' => $organization['founding_date'],
            'telephone' => $organization['telephone'],
            'email' => $organization['email'],
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $address['street'],
                'addressLocality' => $address['locality'],
                'addressRegion' => $address['region'],
                'postalCode' => $address['postal_code'],
                'addressCountry' => $address['country'],
            ],
            'sameAs' => $organization['same_as'],
        ];
    }

    public function website(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => $this->url('#website'),
            'url' => $this->url(),
            'name' => config('seo.site_name'),
            'inLanguage' => 'id',
            'publisher' => ['@id' => $this->organizationId()],
        ];
    }

    public function breadcrumbs(array $trail): array
    {
        $items = [];

        foreach (array_values($trail) as $index => [$name, $path]) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => (string) $name,
                'item' => $this->url($path),
            ];
        }

        return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    public function newsArticle(Berita $berita): array
    {
        return [
            '@type' => 'NewsArticle',
            'mainEntityOfPage' => $this->url('berita/'.$berita->slug),
            'headline' => Str::limit($berita->title, self::HEADLINE_LIMIT, ''),
            'description' => $this->description($berita->deskripsi),
            'image' => [$this->storageUrl($berita->gambar)],
            'datePublished' => $berita->created_at->toAtomString(),
            'dateModified' => $berita->updated_at->toAtomString(),
            'inLanguage' => 'id',
            'author' => $this->publisher(),
            'publisher' => $this->publisher(),
        ];
    }

    public function program(Jurusan $jurusan): array
    {
        return [
            '@type' => 'EducationalOccupationalProgram',
            'name' => $jurusan->jurusan,
            'description' => $this->description($jurusan->deskripsi),
            'url' => $this->url('jurusan/'.$jurusan->slug),
            'inLanguage' => 'id',
            'provider' => ['@id' => $this->organizationId()],
        ];
    }

    public function graph(array $nodes = []): string
    {
        return json_encode(
            [
                '@context' => 'https://schema.org',
                '@graph' => array_merge([$this->organization(), $this->website()], array_values($nodes)),
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }

    private function organizationId(): string
    {
        return $this->url('#organization');
    }

    private function publisher(): array
    {
        return [
            '@type' => 'Organization',
            'name' => config('seo.organization.name'),
            'url' => $this->url(),
            'logo' => ['@type' => 'ImageObject', 'url' => $this->asset(config('seo.organization.logo'))],
        ];
    }
}
