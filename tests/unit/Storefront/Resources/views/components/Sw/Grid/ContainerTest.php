<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Resources\views\components\Sw\Grid;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter;
use Shopware\Storefront\Resources\views\components\Sw\Grid\Container;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Container::class)]
class ContainerTest extends TestCase
{
    #[DataProvider('wrapperClassProvider')]
    public function testAppliesBorderToBothWrappersAndAlignmentToTheGrid(
        string $border,
        string $variant,
        string $alignment,
        string $expectedBorderClasses,
        string $expectedAlignmentClass,
    ): void {
        $component = new Container(new UrlEncodingTwigFilter());
        $component->mode = 'max-content';
        $component->border = $border;
        $component->borderVariant = $variant;
        $component->borderFullWidth = true;
        $component->justifyContent = $alignment;

        $presentation = $component->getPresentation();

        static::assertSame('sw-grid-container ' . $expectedBorderClasses . ' border-full-width', $presentation['outer']['class']);
        static::assertSame(
            'sw-grid-container__inner ' . $expectedBorderClasses . ' ' . $expectedAlignmentClass . ' max-content has-grid-shadow',
            $presentation['inner']['class'],
        );
    }

    /**
     * @return iterable<string, array{string, string, string, string, string}>
     */
    public static function wrapperClassProvider(): iterable
    {
        yield 'all sides and direct alignment' => ['all', 'primary', 'center', 'border border-primary', 'justify-content-center'];
        yield 'one side and space around' => ['start', 'secondarySubtle', 'spaceAround', 'border-start border-secondary-subtle', 'justify-content-space-around'];
        yield 'space between' => ['bottom', 'secondary', 'spaceBetween', 'border-bottom border-secondary', 'justify-content-space-between'];
        yield 'space evenly' => ['end', 'primary', 'spaceEvenly', 'border-end border-primary', 'justify-content-space-evenly'];
    }

    public function testDefaultsNullGapAndIndependentInstances(): void
    {
        $first = new Container(new UrlEncodingTwigFilter());
        $second = new Container(new UrlEncodingTwigFilter());
        $first->mount(null);
        $second->mount();

        static::assertSame(24, $first->gap);
        static::assertSame($second->getPresentation(), $first->getPresentation());
        static::assertSame('', $first->getPresentation()['placementRules']);

        $first->gridLayout = ['lg' => ['columns' => [['type' => 'auto'], ['type' => 'auto']], 'areas' => [['a', 'b']]]];
        $first->gap = 12;
        static::assertNotSame($second->getPresentation(), $first->getPresentation());
        static::assertSame(24, $second->gap);
    }

    public function testMultiRowAreasAndSparseBreakpoints(): void
    {
        $layout = [
            'xs' => ['columns' => [['type' => 'auto'], ['type' => 'auto']], 'areas' => [['a', 'a'], ['b', 'c'], ['b', 'd']]],
            'sm' => ['columns' => [['type' => 'auto']], 'areas' => [['mobile']]],
            'lg' => ['columns' => [['type' => 'px', 'value' => 200], ['type' => 'fraction', 'value' => 6]], 'areas' => [['e', 'e'], ['f', 'g']]],
        ];
        $component = new Container(new UrlEncodingTwigFilter());
        $component->gridLayout = $layout;
        $result = $component->getPresentation();
        static::assertStringContainsString(':nth-child(4n + 1 of ', $result['placementRules']);
        static::assertStringContainsString('--sw-grid-item-column-xs:1 / span 2;--sw-grid-item-row-xs:span 1;', $result['placementRules']);
        static::assertStringContainsString('--sw-grid-item-column-xs:1 / span 1;--sw-grid-item-row-xs:span 2;', $result['placementRules']);
        static::assertStringContainsString('.display-md', $result['placementRules']);
        static::assertStringContainsString(':not(.d-md-hidden):not(:has(> .grid-column.d-md-hidden))', $result['placementRules']);
        static::assertStringContainsString(':nth-child(3n + 1 of ', $result['placementRules']);
        static::assertStringNotContainsString('--sw-grid-item-column-sm', $result['placementRules']);
        static::assertStringContainsString('--sw-grid-columns-sm: minmax(0, 1fr);', $result['inner']['style'] ?? '');
        static::assertStringContainsString('--sw-grid-columns-md: min(200px', $result['inner']['style'] ?? '');
        static::assertStringContainsString('--sw-grid-columns-xxl: min(200px', $result['inner']['style'] ?? '');
        static::assertStringContainsString('--sw-grid-item-column-md:2 / span 1;--sw-grid-item-row-md:span 1;', $result['placementRules']);
        static::assertStringContainsString('--sw-grid-columns-lg: min(200px', $result['inner']['style'] ?? '');
    }

    public function testEdgeBreakpointsInheritTheirNeighbourBeforeTheBaseGrid(): void
    {
        $component = new Container(new UrlEncodingTwigFilter());
        $component->gridLayout = [
            'lg' => ['columns' => [['type' => 'auto'], ['type' => 'auto']], 'areas' => [['a1', 'a2']]],
            'sm' => ['columns' => [['type' => 'auto']], 'areas' => [['a1']]],
            'xl' => ['columns' => [['type' => 'auto'], ['type' => 'auto'], ['type' => 'auto']], 'areas' => [['a1', 'a2', 'a3']]],
        ];

        $style = $component->getPresentation()['inner']['style'] ?? '';

        static::assertStringContainsString('--sw-grid-columns-xs: minmax(0, 1fr);', $style);
        static::assertStringContainsString('--sw-grid-columns-md: minmax(0, 1fr) minmax(0, 1fr);', $style);
        static::assertStringContainsString('--sw-grid-columns-xxl: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr);', $style);
    }

    public function testInvalidWidthsAndEmptyAreasUseSafeFallbacks(): void
    {
        $layout = ['lg' => [
            'columns' => [['type' => 'px', 'value' => 0], ['type' => 'percent', 'value' => 101], ['type' => 'fraction', 'value' => 13], ['type' => 'unknown', 'value' => 5]],
            'areas' => [],
        ]];
        $component = new Container(new UrlEncodingTwigFilter());
        $component->gridLayout = $layout;
        $result = $component->getPresentation();
        static::assertStringContainsString('--sw-grid-columns-xs: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr);', $result['inner']['style'] ?? '');
        static::assertSame('', $result['placementRules']);
    }

    public function testAutoFitUsesVariablesWithoutGeneratingPlacementRules(): void
    {
        $component = new Container(new UrlEncodingTwigFilter());
        $component->mode = 'auto-fit';
        $component->itemMinWidth = '25rem';
        $component->mount(0);

        $presentation = $component->getPresentation('gap: 2px;');

        static::assertSame('', $presentation['placementRules']);
        static::assertSame('sw-grid-container__inner auto-fit has-grid-shadow', $presentation['inner']['class']);
        static::assertArrayHasKey('style', $presentation['inner']);
        static::assertStringContainsString('--sw-grid-container-item-min-width: 25rem;', $presentation['inner']['style']);
        static::assertStringNotContainsString('--sw-grid-container-gap:', $presentation['inner']['style']);
        static::assertStringEndsWith('gap: 2px;', $presentation['inner']['style']);
    }

    public function testFullWidthBackgroundAndResponsiveSpacingTargetTheCorrectWrappers(): void
    {
        $media = new MediaEntity();
        $media->setPath('media/grid.png');
        $media->setUrl('https://example.com/media/grid%22.png?size=2&v=1');

        $component = new Container(new UrlEncodingTwigFilter());
        $component->mode = 'max-content';
        $component->backgroundFullWidth = true;
        $component->backgroundColor = '#ff0000';
        $component->backgroundOpacity = 50;
        $component->backgroundImage = $media;
        $component->backgroundImageMode = 'contain';
        $component->padding = ['lg' => '8px', 'xl' => '16px'];
        $component->margin = ['lg' => '4px', 'sm' => '0'];
        $component->borderRadius = '8px';
        $component->shadowInset = true;
        $component->shadowOffsetX = -2;

        $presentation = $component->getPresentation();

        static::assertSame('', $presentation['placementRules']);
        static::assertSame('sw-grid-container has-grid-background-color has-grid-background-image grid-background-contain', $presentation['outer']['class']);
        static::assertArrayHasKey('style', $presentation['outer']);
        static::assertStringContainsString(
            '--sw-grid-container-background-image: url("https\3A \2F \2F example\2E com\2F media\2F grid\25 22\2E png\3F size\3D 2\26 v\3D 1");',
            $presentation['outer']['style'],
        );
        static::assertStringContainsString('--sw-grid-container-background-opacity: 50%;', $presentation['outer']['style']);
        static::assertArrayHasKey('style', $presentation['inner']);
        static::assertStringContainsString('--sw-grid-container-margin-xs: 0;', $presentation['outer']['style']);
        static::assertStringNotContainsString('--sw-grid-container-padding', $presentation['outer']['style']);
        static::assertSame('sw-grid-container__inner max-content has-grid-radius has-grid-shadow', $presentation['inner']['class']);
        static::assertStringContainsString('--sw-grid-container-margin-xs: 0;', $presentation['inner']['style']);
        static::assertStringContainsString('--sw-grid-container-padding-xxl: 16px;', $presentation['inner']['style']);
        static::assertStringContainsString('--sw-grid-container-border-radius: 8px;', $presentation['inner']['style']);
        static::assertStringContainsString('--sw-grid-container-shadow-offsets: inset -2px 0px 0px 0px;', $presentation['inner']['style']);
        static::assertStringNotContainsString('--sw-grid-container-background', $presentation['inner']['style']);
    }

    public function testEmitsNoPlacementRulesForAnAllSingleCellPattern(): void
    {
        $component = new Container(new UrlEncodingTwigFilter());
        $component->gridLayout = ['lg' => [
            'columns' => array_fill(0, 3, ['type' => 'auto']),
            'areas' => [['a1', 'a2', 'a3'], ['a4', 'a5', 'a6']],
        ]];

        static::assertSame('', $component->getPresentation()['placementRules']);
    }

    public function testEmitsPlacementRulesOnlyForBreakpointsWithSpanningAreas(): void
    {
        $component = new Container(new UrlEncodingTwigFilter());
        $component->gridLayout = [
            'lg' => ['columns' => array_fill(0, 2, ['type' => 'auto']), 'areas' => [['a1', 'a2']]],
            'sm' => ['columns' => array_fill(0, 2, ['type' => 'auto']), 'areas' => [['a1', 'a1'], ['a2', 'a3']]],
        ];

        $rules = $component->getPresentation()['placementRules'];

        static::assertStringContainsString('--sw-grid-item-column-sm:1 / span 2;', $rules);
        static::assertStringContainsString('--sw-grid-item-column-xs:1 / span 2;', $rules);
        foreach (['md', 'lg', 'xl', 'xxl'] as $breakpoint) {
            static::assertStringNotContainsString('--sw-grid-item-column-' . $breakpoint, $rules);
        }
    }

    public function testOutputGrowsLinearlyWithDistinctAreas(): void
    {
        $component = new Container(new UrlEncodingTwigFilter());
        $sizes = [];
        foreach ([4, 8, 16] as $count) {
            $cells = ['a1', ...array_map(static fn (int $i): string => 'a' . $i, range(1, $count))];
            $layout = ['lg' => ['columns' => array_fill(0, $count + 1, ['type' => 'auto']), 'areas' => [$cells]]];
            $component->gridLayout = $layout;
            $sizes[] = \strlen($component->getPresentation()['placementRules']);
        }
        static::assertLessThan($sizes[0] * 2.2, $sizes[1]);
        static::assertLessThan($sizes[1] * 2.2, $sizes[2]);
    }
}
