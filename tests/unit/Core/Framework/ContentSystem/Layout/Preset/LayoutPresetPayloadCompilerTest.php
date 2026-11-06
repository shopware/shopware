<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto\LayoutPresetSpecificationDto;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutPresetPayloadCompiler::class)]
class LayoutPresetPayloadCompilerTest extends TestCase
{
    #[TestDox('carries the component through, mints a hex id, and copies properties verbatim')]
    public function testCompileCarriesComponentMintsIdAndCopiesProperties(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $compiler->compile([
            ['component' => 'Sw:Content:Text', 'properties' => ['text' => '<p>hi</p>']],
        ]);

        static::assertCount(1, $captured);
        static::assertSame('Sw:Content:Text', $captured[0]['component']);
        static::assertSame(['text' => '<p>hi</p>'], $captured[0]['properties']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $captured[0]['id']);
    }

    #[TestDox('recurses into slots keyed by slot name, minting ids at every level')]
    public function testCompileRecursesIntoSlots(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $compiler->compile([
            [
                'component' => 'Sw:Grid:Container',
                'slots' => [
                    'content' => [
                        ['component' => 'Sw:Media:Image'],
                        ['component' => 'Sw:Content:Text', 'properties' => ['text' => 'x']],
                    ],
                ],
            ],
        ]);

        $container = $captured[0];
        static::assertSame('Sw:Grid:Container', $container['component']);
        static::assertArrayHasKey('content', $container['slots']);

        $children = $container['slots']['content'];
        static::assertCount(2, $children);
        static::assertSame('Sw:Media:Image', $children[0]['component']);
        static::assertSame('Sw:Content:Text', $children[1]['component']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $children[0]['id']);
        static::assertNotSame($container['id'], $children[0]['id']);
    }

    #[TestDox('carries the authored style through to the draft element verbatim')]
    public function testCompileCarriesStyle(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $style = [
            'col-span' => ['xs' => 4, 'sm' => 4, 'md' => 4, 'lg' => 3, 'xl' => 3, 'xxl' => 3],
            'display' => 3,
        ];

        $compiler->compile([
            ['component' => 'Sw:Product:Listing', 'style' => $style],
        ]);

        static::assertSame($style, $captured[0]['style']);
    }

    #[TestDox('leaves a malformed slot structure for the decoder to reject, still minting the element id')]
    public function testPassesMalformedSlotsThroughToDecoder(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $compiler->compile([
            ['component' => 'Sw:Grid:Container', 'slots' => 'nope'],
            ['component' => 'Sw:Grid:Container', 'slots' => ['content' => 'nope']],
        ]);

        static::assertSame('nope', $captured[0]['slots']);
        static::assertSame(['content' => 'nope'], $captured[1]['slots']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $captured[0]['id']);
        static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $captured[1]['id']);
    }

    #[TestDox('re-encodes the decoded elements into the served payload')]
    public function testCompileEncodesDecodedElements(): void
    {
        $decoder = static::createStub(DraftLayoutDecoder::class);
        $decoder->method('decode')->willReturn([new StoredElement('el-1', 'Sw:Content:Text')]);

        $result = $this->createCompiler($decoder)->compile([['component' => 'Sw:Content:Text']]);

        static::assertSame([
            ['id' => 'el-1', 'component' => 'Sw:Content:Text', 'properties' => []],
        ], $result);
    }

    #[TestDox('an empty layout compiles to an empty payload')]
    public function testEmptyLayoutCompilesToEmptyPayload(): void
    {
        $decoder = static::createStub(DraftLayoutDecoder::class);
        $decoder->method('decode')->willReturn([]);

        static::assertSame([], $this->createCompiler($decoder)->compile([]));
    }

    #[TestDox('replaces a preset reference in place with all elements of the referenced preset')]
    public function testCompileExpandsAPresetReferenceInPlace(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $compiler->compile([
            ['component' => 'Sw:Media:Gallery'],
            ['preset' => 'Sw:MediaAndText'],
            ['component' => 'Sw:Product:Slider'],
        ], [
            'Sw:MediaAndText' => $this->preset([['component' => 'Sw:Media:Image'], ['component' => 'Sw:Content:Text']]),
        ]);

        static::assertSame(
            ['Sw:Media:Gallery', 'Sw:Media:Image', 'Sw:Content:Text', 'Sw:Product:Slider'],
            array_column($captured, 'component'),
        );
        static::assertCount(4, array_unique(array_column($captured, 'id')));
    }

    #[TestDox('expands every preset reference of one list at its own position')]
    public function testCompileExpandsSeveralReferencesInOneList(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $compiler->compile([
            ['preset' => 'Sw:Pair'],
            ['component' => 'Sw:Media:Gallery'],
            ['preset' => 'Sw:Pair'],
        ], [
            'Sw:Pair' => $this->preset([['component' => 'Sw:Media:Image'], ['component' => 'Sw:Content:Text']]),
        ]);

        static::assertSame(
            ['Sw:Media:Image', 'Sw:Content:Text', 'Sw:Media:Gallery', 'Sw:Media:Image', 'Sw:Content:Text'],
            array_column($captured, 'component'),
        );
        static::assertCount(5, array_unique(array_column($captured, 'id')));
    }

    #[TestDox('expands a preset reference inside a slot and a reference inside a referenced preset')]
    public function testCompileExpandsReferencesInSlotsAndNestedPresets(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $compiler->compile([
            [
                'component' => 'Sw:Grid:Container',
                'slots' => ['content' => [['preset' => 'Sw:Outer'], ['component' => 'Sw:Product:Slider']]],
            ],
        ], [
            'Sw:Outer' => $this->preset([['component' => 'Sw:Media:Gallery'], ['preset' => 'Sw:Inner']]),
            'Sw:Inner' => $this->preset([['component' => 'Sw:Content:Text']]),
        ]);

        static::assertSame(
            ['Sw:Media:Gallery', 'Sw:Content:Text', 'Sw:Product:Slider'],
            array_column($captured[0]['slots']['content'], 'component'),
        );
    }

    #[TestDox('throws with the reference chain when a preset reaches itself again')]
    public function testCompileThrowsOnCircularReference(): void
    {
        $compiler = $this->createCompiler(static::createStub(DraftLayoutDecoder::class));

        $this->expectExceptionObject(ContentSystemException::layoutPresetCircularReference(['Sw:A', 'Sw:B', 'Sw:A']));

        $compiler->compile([['preset' => 'Sw:B']], [
            'Sw:A' => $this->preset([['preset' => 'Sw:B']]),
            'Sw:B' => $this->preset([['component' => 'Sw:Grid:Container', 'slots' => ['content' => [['preset' => 'Sw:A']]]]]),
        ], ['Sw:A']);
    }

    #[TestDox('throws with the reference chain when a referenced preset does not exist')]
    public function testCompileThrowsOnUnknownReference(): void
    {
        $compiler = $this->createCompiler(static::createStub(DraftLayoutDecoder::class));

        $this->expectExceptionObject(ContentSystemException::layoutPresetUnknownReference('Sw:Missing', ['Sw:A', 'Sw:B']));

        $compiler->compile([['preset' => 'Sw:B']], [
            'Sw:B' => $this->preset([['component' => 'Sw:Grid:Container', 'slots' => ['content' => [['preset' => 'Sw:Missing']]]]]),
        ], ['Sw:A']);
    }

    #[TestDox('throws before expanding more nested presets than the limit')]
    public function testCompileThrowsWhenTheExpansionExceedsTheLimit(): void
    {
        $presets = ['Sw:Level40' => $this->preset([['component' => 'Sw:Content:Text']])];
        for ($level = 39; $level >= 0; --$level) {
            $next = 'Sw:Level' . ($level + 1);
            $presets['Sw:Level' . $level] = $this->preset([['preset' => $next], ['preset' => $next]]);
        }

        $compiler = $this->createCompiler(static::createStub(DraftLayoutDecoder::class));

        $this->expectExceptionObject(ContentSystemException::layoutPresetTooManyReferences('Sw:Level0', LayoutPresetPayloadCompiler::MAX_EXPANDED_PRESETS));

        $compiler->compile($presets['Sw:Level0']->layout, $presets, ['Sw:Level0']);
    }

    #[TestDox('expands the same preset twice side by side without treating it as circular')]
    public function testCompileAllowsRepeatedNonCircularReferences(): void
    {
        $captured = [];
        $compiler = $this->createCompiler($this->capturingDecoder($captured));

        $compiler->compile([['preset' => 'Sw:Leaf'], ['preset' => 'Sw:Leaf']], [
            'Sw:Leaf' => $this->preset([['component' => 'Sw:Content:Text']]),
        ], ['Sw:Root']);

        static::assertCount(2, $captured);
    }

    /**
     * @param list<array<string, mixed>> $layout
     */
    private function preset(array $layout): LayoutPresetSpecificationDto
    {
        return new LayoutPresetSpecificationDto('Preset', 'A preset.', 'regular-box', $layout);
    }

    /**
     * @param array<int, mixed> $captured
     */
    private function capturingDecoder(array &$captured): DraftLayoutDecoder
    {
        $decoder = static::createStub(DraftLayoutDecoder::class);
        $decoder->method('decode')->willReturnCallback(static function (array $draft) use (&$captured): array {
            $captured = $draft;

            return [];
        });

        return $decoder;
    }

    private function createCompiler(DraftLayoutDecoder $decoder): LayoutPresetPayloadCompiler
    {
        return new LayoutPresetPayloadCompiler(
            $decoder,
            new StoredElementCodec(static::createStub(DataLoaderConfigSerializerProvider::class)),
        );
    }
}
