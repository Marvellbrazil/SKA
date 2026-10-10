<?php

declare(strict_types=1);

const SCAN_DIRECTORIES = ['resources', 'app', 'database/seeders', 'config', 'routes'];

const STYLE_MODIFIERS = ['fa-solid', 'fa-regular', 'fa-brands', 'fa-classic', 'fa-fw', 'fa-spin', 'fa-lg', 'fa-xs', 'fa-sm', 'fa-xl', 'fa-2xl', 'fa-2x', 'fa-3x', 'fa-4x', 'fa-5x'];

const FONT_FACES = [
    ['family' => 'Font Awesome 7 Free', 'weight' => 900, 'file' => 'fa-solid-900'],
    ['family' => 'Font Awesome 7 Free', 'weight' => 400, 'file' => 'fa-regular-400'],
    ['family' => 'Font Awesome 7 Brands', 'weight' => 400, 'file' => 'fa-brands-400'],
];

function scanIconTokens(string $root): array
{
    $tokens = [];

    foreach (SCAN_DIRECTORIES as $directory) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $name = $file->getFilename();

            if (preg_match('/\.(php|js|ts|css)$/', $name) !== 1 || $name === 'icons.css') {
                continue;
            }

            preg_match_all('/\bfa-[a-z0-9-]+/', (string) file_get_contents($file->getPathname()), $matches);

            foreach ($matches[0] as $token) {
                $tokens[$token] = true;
            }
        }
    }

    return array_keys($tokens);
}

function parseGlyphs(string $css): array
{
    $glyphs = [];

    preg_match_all('/((?:\.fa-[a-z0-9-]+\s*,\s*)*\.fa-[a-z0-9-]+)\s*\{\s*--fa:\s*"([^"]+)"/', $css, $blocks, PREG_SET_ORDER);

    foreach ($blocks as [, $selectors, $codepoint]) {
        foreach (preg_split('/\s*,\s*/', trim($selectors)) as $selector) {
            $glyphs[ltrim($selector, '.')] = $codepoint;
        }
    }

    return $glyphs;
}

function subsetFont(string $source, string $target, array $unicodes): void
{
    $command = sprintf(
        'uv run --quiet --with fonttools --with brotli pyftsubset %s --unicodes=%s --flavor=woff2 --layout-features= --no-hinting --output-file=%s 2>&1',
        escapeshellarg($source),
        escapeshellarg(implode(',', $unicodes)),
        escapeshellarg($target)
    );

    exec($command, $output, $status);

    if ($status !== 0) {
        fwrite(STDERR, implode("\n", $output)."\n");
        exit(1);
    }
}

$root = dirname(__DIR__);
$package = $root.'/node_modules/@fortawesome/fontawesome-free';
$fontDirectory = $root.'/resources/fonts/fontawesome';
$glyphs = parseGlyphs((string) file_get_contents($package.'/css/all.css'));

$rules = [];
$unresolved = [];

foreach (scanIconTokens($root) as $token) {
    if (in_array($token, STYLE_MODIFIERS, true)) {
        continue;
    }

    if (isset($glyphs[$token])) {
        $rules[$token] = $glyphs[$token];
    } else {
        $unresolved[] = $token;
    }
}

ksort($rules);

$unicodes = array_values(array_unique(array_map(fn (string $codepoint): string => 'U+'.ltrim($codepoint, '\\'), $rules)));

if (! is_dir($fontDirectory)) {
    mkdir($fontDirectory, 0777, true);
}

$css = '';

foreach (FONT_FACES as $face) {
    subsetFont($package.'/webfonts/'.$face['file'].'.woff2', $fontDirectory.'/'.$face['file'].'.woff2', $unicodes);

    $css .= sprintf(
        "@font-face{font-family:\"%s\";font-style:normal;font-weight:%d;font-display:swap;src:url(\"../fonts/fontawesome/%s.woff2\") format(\"woff2\")}\n",
        $face['family'],
        $face['weight'],
        $face['file']
    );
}

$css .= <<<'CSS'
.fa,.fas,.far,.fab,.fa-solid,.fa-regular,.fa-brands{display:inline-block;font-family:"Font Awesome 7 Free";font-style:normal;font-weight:900;font-variant:normal;line-height:1;text-align:center;text-rendering:auto;width:1.25em;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.far,.fa-regular{font-weight:400}
.fab,.fa-brands{font-family:"Font Awesome 7 Brands";font-weight:400}
:is(.fa,.fas,.far,.fab,.fa-solid,.fa-regular,.fa-brands)::before{content:var(--fa)}
@supports (content:""/""){:is(.fa,.fas,.far,.fab,.fa-solid,.fa-regular,.fa-brands)::before{content:var(--fa)/""}}

CSS;

foreach ($rules as $token => $codepoint) {
    $css .= sprintf(".%s{--fa:\"%s\"}\n", $token, $codepoint);
}

file_put_contents($root.'/resources/css/icons.css', $css);

printf("icons resolved: %d, unresolved: %d%s\n", count($rules), count($unresolved), $unresolved === [] ? '' : ' ('.implode(' ', $unresolved).')');
printf("css bytes: %d\n", strlen($css));

foreach (FONT_FACES as $face) {
    printf("%s: %d -> %d bytes\n", $face['file'], filesize($package.'/webfonts/'.$face['file'].'.woff2'), filesize($fontDirectory.'/'.$face['file'].'.woff2'));
}
