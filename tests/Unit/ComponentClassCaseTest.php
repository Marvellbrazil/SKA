<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Support\Str;
use PHPUnit\Framework\TestCase;

class ComponentClassCaseTest extends TestCase
{
    private const COMPONENT_DIRECTORY = __DIR__.'/../../app/View/Components';

    public function test_every_component_file_matches_its_class_name_exactly(): void
    {
        $mismatches = [];

        foreach (glob(self::COMPONENT_DIRECTORY.'/*.php') as $path) {
            $file = basename($path, '.php');
            preg_match('/^class\s+(\w+)\s+extends\s+Component/m', (string) file_get_contents($path), $match);

            if (($match[1] ?? null) !== $file) {
                $mismatches[] = "{$file}.php declares class ".($match[1] ?? '?');
            }
        }

        $this->assertSame([], $mismatches);
    }

    public function test_components_that_prepare_view_data_use_a_pascal_case_file_name(): void
    {
        $broken = [];

        foreach (glob(self::COMPONENT_DIRECTORY.'/*.php') as $path) {
            $file = basename($path, '.php');
            $preparesViewData = preg_match('/public\s+(?:readonly\s+)?(?:\??string|bool|array|int)\s+\$/', (string) file_get_contents($path)) === 1;

            if ($preparesViewData && $file !== Str::studly($file)) {
                $broken[] = $file;
            }
        }

        $this->assertSame([], $broken, 'on a case-sensitive filesystem Laravel cannot autoload these, so their view renders without its variables');
    }
}
