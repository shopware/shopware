<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Element\Style;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\BoxSpacingNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyleNormalizer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Registry\AbstractContentSystemStyleOptionRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionValueType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ElementStyleNormalizer::class)]
class ElementStyleNormalizerTest extends TestCase
{
    #[TestDox('wraps a breakpoint-aware scalar into the full breakpoint map')]
    public function testWrapsBreakpointAwareScalarIntoBreakpointMap(): void
    {
        $normalized = $this->normalizer($this->textOption('padding'))
            ->normalize(new ElementStyle(['padding' => '0 8px']));

        static::assertSame([
            'padding' => [
                'xs' => '0 8px',
                'sm' => '0 8px',
                'md' => '0 8px',
                'lg' => '0 8px',
                'xl' => '0 8px',
                'xxl' => '0 8px',
            ],
        ], $normalized->toArray());
    }

    #[TestDox('normalizes every entry of a box-spacing breakpoint map to explicit CSS')]
    public function testNormalizesBoxSpacingBreakpointMapEntriesToExplicitCss(): void
    {
        $normalized = $this->normalizer($this->boxSpacingOption('padding'))->normalize(new ElementStyle([
            'padding' => [
                'xs' => 20,
                'sm' => 20,
                'md' => '20px 40px 20px 40px',
                'lg' => '20px 40px 20px 40px',
                'xl' => '20px 40px 20px 40px',
                'xxl' => '20px 40px 20px 40px',
            ],
        ]));

        static::assertSame([
            'padding' => [
                'xs' => '20px 20px 20px 20px',
                'sm' => '20px 20px 20px 20px',
                'md' => '20px 40px 20px 40px',
                'lg' => '20px 40px 20px 40px',
                'xl' => '20px 40px 20px 40px',
                'xxl' => '20px 40px 20px 40px',
            ],
        ], $normalized->toArray());
    }

    #[TestDox('wraps a normalized box-spacing scalar into the full breakpoint map')]
    public function testWrapsNormalizedBoxSpacingScalarIntoBreakpointMap(): void
    {
        $normalized = $this->normalizer($this->boxSpacingOption('padding'))
            ->normalize(new ElementStyle(['padding' => '20px 40px 20px 40px']));

        static::assertSame([
            'padding' => [
                'xs' => '20px 40px 20px 40px',
                'sm' => '20px 40px 20px 40px',
                'md' => '20px 40px 20px 40px',
                'lg' => '20px 40px 20px 40px',
                'xl' => '20px 40px 20px 40px',
                'xxl' => '20px 40px 20px 40px',
            ],
        ], $normalized->toArray());
    }

    #[TestDox('keeps a span map in which not every entry is the range minimum')]
    public function testKeepsMixedSpanMapWhenNotAllValuesAreTheMinimum(): void
    {
        $normalized = $this->normalizer($this->colSpanOption())
            ->normalize(new ElementStyle(['col-span' => ['lg' => 1, 'xl' => 2]]));

        static::assertSame(['col-span' => ['lg' => 1, 'xl' => 2]], $normalized->toArray());
    }

    #[TestDox('fills the breakpoints a partial map omits with the option default')]
    public function testExpandsPartialMapWithTheOptionDefaultForMissingBreakpoints(): void
    {
        $normalized = $this->normalizer($this->displayOption())
            ->normalize(new ElementStyle(['display' => ['xs' => false, 'sm' => false, 'md' => false]]));

        static::assertSame([
            'display' => [
                'xs' => false,
                'sm' => false,
                'md' => false,
                'lg' => true,
                'xl' => true,
                'xxl' => true,
            ],
        ], $normalized->toArray());
    }

    #[TestDox('keeps the explicit entries of a viewport-specific map while filling the rest')]
    public function testKeepsExplicitValuesWhenExpandingAViewportSpecificMap(): void
    {
        $normalized = $this->normalizer($this->displayOption())
            ->normalize(new ElementStyle(['display' => ['xs' => false, 'lg' => true, 'xl' => true, 'xxl' => true]]));

        static::assertSame([
            'display' => [
                'xs' => false,
                'sm' => true,
                'md' => true,
                'lg' => true,
                'xl' => true,
                'xxl' => true,
            ],
        ], $normalized->toArray());
    }

    #[TestDox('keeps a breakpoint map unchanged for an option that declares no default')]
    public function testKeepsBreakpointMapUnchangedForAnOptionWithoutDefault(): void
    {
        $normalized = $this->normalizer($this->colSpanOption())
            ->normalize(new ElementStyle(['col-span' => ['lg' => 6]]));

        static::assertSame(['col-span' => ['lg' => 6]], $normalized->toArray());
    }

    #[TestDox('keeps a scalar flat for an option that is not breakpoint-aware')]
    public function testKeepsScalarFlatForAnOptionThatIsNotBreakpointAware(): void
    {
        $normalized = $this->normalizer($this->zIndexOption())->normalize(new ElementStyle(['z-index' => 10]));

        static::assertSame(['z-index' => 10], $normalized->toArray());
    }

    #[TestDox('passes an option with no registry entry through untouched')]
    public function testPassesAnUnregisteredOptionThroughUntouched(): void
    {
        $normalized = $this->normalizer()->normalize(new ElementStyle(['brand-gap' => '20']));

        static::assertSame(['brand-gap' => '20'], $normalized->toArray());
    }

    #[TestDox('normalizes a whole style map, dropping the options that resolve to unset')]
    public function testNormalizesAWholeStyleMap(): void
    {
        $normalized = $this->normalizer(
            $this->textOption('padding'),
            $this->colSpanOption(),
            $this->textOption('margin'),
            $this->alignSelfOption(),
        )->normalize(new ElementStyle([
            'padding' => '0 8px',
            'col-span' => ['lg' => 6],
            'margin' => '',
            'align-self' => ['md' => 'auto'],
        ]));

        static::assertSame([
            'padding' => [
                'xs' => '0 8px',
                'sm' => '0 8px',
                'md' => '0 8px',
                'lg' => '0 8px',
                'xl' => '0 8px',
                'xxl' => '0 8px',
            ],
            'col-span' => ['lg' => 6],
        ], $normalized->toArray());
    }

    #[TestDox('applies shorthand normalization to an option that declares kind box-spacing')]
    public function testBoxSpacingKindSelectsShorthandNormalization(): void
    {
        $normalized = $this->normalizer($this->boxSpacingOption('padding'))
            ->normalize(new ElementStyle(['padding' => '20']));

        static::assertSame([
            'padding' => [
                'xs' => '20px 20px 20px 20px',
                'sm' => '20px 20px 20px 20px',
                'md' => '20px 20px 20px 20px',
                'lg' => '20px 20px 20px 20px',
                'xl' => '20px 20px 20px 20px',
                'xxl' => '20px 20px 20px 20px',
            ],
        ], $normalized->toArray());
    }

    #[TestDox('leaves the value verbatim for an option that declares no kind')]
    public function testOptionWithoutKindLeavesTheValueVerbatim(): void
    {
        $normalized = $this->normalizer($this->textOption('padding'))
            ->normalize(new ElementStyle(['padding' => '20']));

        static::assertSame([
            'padding' => [
                'xs' => '20',
                'sm' => '20',
                'md' => '20',
                'lg' => '20',
                'xl' => '20',
                'xxl' => '20',
            ],
        ], $normalized->toArray());
    }

    #[TestDox('leaves the value verbatim for an option whose adminUI component is box-spacing but declares no kind')]
    public function testBoxSpacingAdminUiComponentWithoutKindIsNotTreatedAsBoxSpacing(): void
    {
        $normalized = $this->normalizer($this->boxSpacingAdminUiWithoutKindOption('padding'))
            ->normalize(new ElementStyle(['padding' => '20']));

        static::assertSame([
            'padding' => [
                'xs' => '20',
                'sm' => '20',
                'md' => '20',
                'lg' => '20',
                'xl' => '20',
                'xxl' => '20',
            ],
        ], $normalized->toArray());
    }

    #[TestDox('omits an option whose value is the empty string')]
    public function testOmitsEmptyStringValue(): void
    {
        $normalized = $this->normalizer($this->textOption('padding'))
            ->normalize(new ElementStyle(['padding' => '']));

        static::assertSame([], $normalized->toArray());
    }

    #[TestDox('omits an option whose value equals its declared default')]
    public function testOmitsValueEqualToTheOptionDefault(): void
    {
        $normalized = $this->normalizer($this->alignSelfOption())
            ->normalize(new ElementStyle(['align-self' => 'auto']));

        static::assertSame([], $normalized->toArray());
    }

    #[TestDox('omits a breakpoint map whose only entry is the empty string')]
    public function testOmitsBreakpointMapWhoseOnlyEntryIsAnEmptyString(): void
    {
        $normalized = $this->normalizer($this->textOption('padding'))
            ->normalize(new ElementStyle(['padding' => ['lg' => '']]));

        static::assertSame([], $normalized->toArray());
    }

    /**
     * @return iterable<string, array{0: array<string, int>}>
     */
    public static function rangeMinimumMapProvider(): iterable
    {
        yield 'every entry is the range minimum' => [['lg' => 1, 'xl' => 1, 'xxl' => 1]];
    }

    /**
     * @param array<string, int> $value
     */
    #[DataProvider('rangeMinimumMapProvider')]
    #[TestDox('omits a breakpoint map whose every entry is the declared range minimum')]
    public function testOmitsBreakpointMapWhoseEntriesAreAllTheRangeMinimum(array $value): void
    {
        $normalized = $this->normalizer($this->colSpanOption())
            ->normalize(new ElementStyle(['col-span' => $value]));

        static::assertSame([], $normalized->toArray());
    }

    #[TestDox('keeps a value equal to the range minimum when the option also declares a default')]
    public function testKeepsRangeMinimumWhenTheOptionAlsoDeclaresADefault(): void
    {
        $option = new StyleOptionSpecification(
            'pin-min',
            new StyleOptionValueType(StyleOptionValueType::TYPE_INTEGER, null, ['min' => 1, 'max' => 12], null, 5),
            false,
            ['component' => 'number'],
        );

        $normalized = $this->normalizer($option)->normalize(new ElementStyle(['pin-min' => 1]));

        static::assertSame(['pin-min' => 1], $normalized->toArray());
    }

    #[TestDox('replaces an empty-string entry with the option default when expanding')]
    public function testExpansionReplacesAnEmptyStringEntryWithTheOptionDefault(): void
    {
        $normalized = $this->normalizer($this->alignSelfOption())
            ->normalize(new ElementStyle(['align-self' => ['xs' => 'start', 'sm' => '']]));

        static::assertSame([
            'align-self' => [
                'xs' => 'start',
                'sm' => 'auto',
                'md' => 'auto',
                'lg' => 'auto',
                'xl' => 'auto',
                'xxl' => 'auto',
            ],
        ], $normalized->toArray());
    }

    #[TestDox('drops map keys that are not canonical breakpoints when expanding')]
    public function testExpansionDropsKeysThatAreNotBreakpoints(): void
    {
        $normalized = $this->normalizer($this->displayOption())
            ->normalize(new ElementStyle(['display' => ['xs' => false, 'print' => false]]));

        static::assertSame([
            'display' => [
                'xs' => false,
                'sm' => true,
                'md' => true,
                'lg' => true,
                'xl' => true,
                'xxl' => true,
            ],
        ], $normalized->toArray());
    }

    #[TestDox('leaves a map unchanged when no canonical breakpoint carries an entry')]
    public function testKeepsMapUnchangedWhenNoBreakpointCarriesAnEntry(): void
    {
        $normalized = $this->normalizer($this->displayOption())
            ->normalize(new ElementStyle(['display' => ['print' => false]]));

        static::assertSame(['display' => ['print' => false]], $normalized->toArray());
    }

    #[TestDox('leaves a map unchanged when every breakpoint carries the same entry')]
    public function testKeepsUniformMapUnchangedWhenEveryBreakpointCarriesTheSameEntry(): void
    {
        $uniform = ['xs' => false, 'sm' => false, 'md' => false, 'lg' => false, 'xl' => false, 'xxl' => false];

        $normalized = $this->normalizer($this->displayOption())->normalize(new ElementStyle(['display' => $uniform]));

        static::assertSame(['display' => $uniform], $normalized->toArray());
    }

    #[TestDox('returns an empty style when every option resolves to unset')]
    public function testReturnsAnEmptyStyleWhenEveryValueIsUnset(): void
    {
        $normalized = $this->normalizer($this->textOption('padding'), $this->alignSelfOption())
            ->normalize(new ElementStyle(['padding' => '', 'align-self' => 'auto']));

        static::assertTrue($normalized->isEmpty());
    }

    /**
     * @return iterable<string, array{0: array<string, string|int|float|bool|array<string, string|int|float|bool>>}>
     */
    public static function idempotenceProvider(): iterable
    {
        yield 'box-spacing scalar' => [['padding' => '20']];
        yield 'box-spacing breakpoint map' => [['padding' => ['xs' => 20, 'md' => '20px 40px 20px 40px']]];
        yield 'span map without default' => [['col-span' => ['lg' => 6]]];
        yield 'partial map with default' => [['display' => ['xs' => false, 'sm' => false]]];
        yield 'uniform map with default' => [['display' => ['xs' => true, 'sm' => true, 'md' => true, 'lg' => true, 'xl' => true, 'xxl' => true]]];
        yield 'flat option' => [['z-index' => 10]];
        yield 'unregistered option' => [['brand-gap' => '20']];
        yield 'align-self and col-span values that survive the first pass' => [['align-self' => 'start', 'col-span' => ['md' => 6]]];
        yield 'mixed style' => [[
            'padding' => '8px 16px',
            'col-span' => ['lg' => 6, 'xl' => 8],
            'display' => ['xs' => false],
            'align-self' => 'start',
            'z-index' => 10,
            'brand-gap' => '4px',
        ]];
    }

    /**
     * @param array<string, string|int|float|bool|array<string, string|int|float|bool>> $values
     */
    #[DataProvider('idempotenceProvider')]
    #[TestDox('changes nothing when normalizing an already normalized style')]
    public function testNormalizingTwiceEqualsNormalizingOnce(array $values): void
    {
        $normalizer = $this->normalizer(
            $this->boxSpacingOption('padding'),
            $this->colSpanOption(),
            $this->displayOption(),
            $this->alignSelfOption(),
            $this->zIndexOption(),
        );

        $once = $normalizer->normalize(new ElementStyle($values))->toArray();

        static::assertSame($once, $normalizer->normalize(new ElementStyle($once))->toArray());
    }

    private function normalizer(StyleOptionSpecification ...$options): ElementStyleNormalizer
    {
        $indexed = [];

        foreach ($options as $option) {
            $indexed[$option->name()] = $option;
        }

        $registry = static::createStub(AbstractContentSystemStyleOptionRegistry::class);
        $registry->method('all')->willReturn($indexed);

        return new ElementStyleNormalizer($registry, new BoxSpacingNormalizer());
    }

    /**
     * Declares the kind and deliberately carries a non-box-spacing adminUI control, so every case built on
     * this fixture varies on the declaration alone: with the box-spacing hint here too, the same assertions
     * would pass under the removed adminUI discriminator and pin nothing.
     */
    private function boxSpacingOption(string $name): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            $name,
            new StyleOptionValueType(StyleOptionValueType::TYPE_STRING, null, null, 64, null),
            true,
            ['component' => 'text', 'label' => 'Padding'],
            kind: StyleOptionSpecification::KIND_BOX_SPACING,
        );
    }

    /**
     * Carries the box-spacing adminUI control but declares no kind: the discriminator is the declaration,
     * not the Administration presentation hint.
     */
    private function boxSpacingAdminUiWithoutKindOption(string $name): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            $name,
            new StyleOptionValueType(StyleOptionValueType::TYPE_STRING, null, null, 64, null),
            true,
            ['component' => 'box-spacing', 'label' => 'Padding'],
        );
    }

    private function textOption(string $name): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            $name,
            new StyleOptionValueType(StyleOptionValueType::TYPE_STRING, null, null, 64, null),
            true,
            ['component' => 'text', 'label' => 'Padding'],
        );
    }

    private function colSpanOption(): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            'col-span',
            new StyleOptionValueType(StyleOptionValueType::TYPE_INTEGER, null, ['min' => 1, 'max' => 12], null, null),
            true,
            ['component' => 'number', 'label' => 'Column Span'],
        );
    }

    private function displayOption(): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            'display',
            new StyleOptionValueType(StyleOptionValueType::TYPE_BOOLEAN, null, null, null, true),
            true,
            ['component' => 'switch', 'label' => 'Display'],
        );
    }

    private function alignSelfOption(): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            'align-self',
            new StyleOptionValueType(
                StyleOptionValueType::TYPE_STRING,
                ['auto', 'start', 'center', 'end', 'stretch', 'baseline'],
                null,
                null,
                'auto',
            ),
            true,
            ['component' => 'radio-panel', 'label' => 'Align Self'],
        );
    }

    private function zIndexOption(): StyleOptionSpecification
    {
        return new StyleOptionSpecification(
            'z-index',
            new StyleOptionValueType(StyleOptionValueType::TYPE_INTEGER, null, null, null, null),
            false,
            ['component' => 'number', 'label' => 'Z Index'],
        );
    }
}
