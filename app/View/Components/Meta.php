<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Services\Seo\SeoService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;

class Meta extends Component
{
    public string $resolvedDescription;

    public string $resolvedImage;

    public string $canonical;

    public string $structuredData;

    /**
     * Create a new component instance.
     */
    public function __construct(
        SeoService $seo,
        Request $request,
        public string $title,
        public ?string $description = null,
        public ?string $image = null,
        public string $type = 'website',
        public bool $noindex = false,
        public array $schema = [],
    ) {
        $this->resolvedDescription = $seo->description($description);
        $this->resolvedImage = $seo->asset($image);
        $this->canonical = $seo->canonical($request);
        $this->structuredData = $noindex ? '' : $seo->graph($schema);
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.meta');
    }
}
