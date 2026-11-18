<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Resources\views\components\Sw\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Resources\views\components\Sw\Grid\Container;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Container::class)]
class ContainerTest extends TestCase
{
    public function testDefaultsNullGapAndIndependentInstances(): void
    {
        $first = new Container();
        $second = new Container();
        $first->mount(null);
        $second->mount();

        static::assertSame(24, $first->gap);
        static::assertSame($second->getGridPattern(), $first->getGridPattern());
        static::assertStringContainsString('--sw-grid-item-column-xs:1 / span 1;--sw-grid-item-row-xs:span 1;', $first->getGridPattern()['rules']);

        $first->gridLayout = ['xs' => ['columns' => [['type' => 'auto'], ['type' => 'auto']], 'areas' => [['a', 'b']]]];
        $first->gap = 12;
        static::assertNotSame($second->getGridPattern(), $first->getGridPattern());
        static::assertSame(24, $second->gap);
    }

    public function testMultiRowAreasAndSparseBreakpoints(): void
    {
        $layout = [
            'xs' => ['columns' => [['type' => 'auto'], ['type' => 'auto']], 'areas' => [['a', 'a'], ['b', 'c'], ['b', 'd']]],
            'lg' => ['columns' => [['type' => 'px', 'value' => 200], ['type' => 'fraction', 'value' => 6]], 'areas' => [['e', 'f']]],
        ];
        $component = new Container();
        $component->gridLayout = $layout;
        $result = $component->getGridPattern();
        static::assertStringContainsString(':nth-child(4n + 1 of ', $result['rules']);
        static::assertStringContainsString('--sw-grid-item-column-xs:1 / span 2;--sw-grid-item-row-xs:span 1;', $result['rules']);
        static::assertStringContainsString('--sw-grid-item-column-xs:1 / span 1;--sw-grid-item-row-xs:span 2;', $result['rules']);
        static::assertStringContainsString('.display-md', $result['rules']);
        static::assertStringContainsString(':not(.d-md-hidden):not(:has(> .grid-column.d-md-hidden))', $result['rules']);
        static::assertStringContainsString(':nth-child(2n + 1 of ', $result['rules']);
        static::assertStringContainsString('--sw-grid-columns-md: minmax(0, 1fr) minmax(0, 1fr);', $result['style']);
        static::assertStringContainsString('--sw-grid-columns-lg: min(200px', $result['style']);
    }

    public function testInvalidWidthsAndEmptyAreasUseSafeFallbacks(): void
    {
        $layout = ['xs' => [
            'columns' => [['type' => 'px', 'value' => 0], ['type' => 'percent', 'value' => 101], ['type' => 'fraction', 'value' => 13], ['type' => 'unknown', 'value' => 5]],
            'areas' => [],
        ]];
        $component = new Container();
        $component->gridLayout = $layout;
        $result = $component->getGridPattern();
        static::assertStringContainsString('--sw-grid-columns-xs: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr);', $result['style']);
        static::assertStringContainsString(':nth-child(1n + 1 of ', $result['rules']);
        static::assertStringContainsString('--sw-grid-item-column-xs:1 / span 1;--sw-grid-item-row-xs:span 1;', $result['rules']);
    }

    public function testOutputGrowsLinearlyWithDistinctAreas(): void
    {
        $component = new Container();
        $sizes = [];
        foreach ([4, 8, 16] as $count) {
            $cells = array_map(static fn (int $i): string => 'a' . $i, range(1, $count));
            $layout = ['xs' => ['columns' => array_fill(0, $count, ['type' => 'auto']), 'areas' => [$cells]]];
            $component->gridLayout = $layout;
            $sizes[] = \strlen($component->getGridPattern()['rules']);
        }
        static::assertLessThan($sizes[0] * 2.2, $sizes[1]);
        static::assertLessThan($sizes[1] * 2.2, $sizes[2]);
    }
}
