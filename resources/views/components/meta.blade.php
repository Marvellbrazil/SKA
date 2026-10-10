<meta charset="UTF-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>{{ $title }}</title>
<meta name="description" content="{{ $resolvedDescription }}">
<meta name="author" content="{{ config('seo.author') }}">
<meta name="robots" content="{{ $noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' }}">
@unless ($noindex)
<link rel="canonical" href="{{ $canonical }}">
@endunless

@if (filled(config('seo.google_verification')))
<meta name="google-site-verification" content="{{ config('seo.google_verification') }}">
@endif

<!-- <meta name="theme-color" content=""> -->

<!-- <meta http-equiv="refresh" content="5; url=https://example.com"> -->

<meta property="og:type" content="{{ $type }}">
<meta property="og:locale" content="{{ config('seo.locale') }}">
<meta property="og:site_name" content="{{ config('seo.site_name') }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $resolvedDescription }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $resolvedImage }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $resolvedDescription }}">
<meta name="twitter:image" content="{{ $resolvedImage }}">

@unless ($noindex)
<script type="application/ld+json">{!! $structuredData !!}</script>
@endunless
