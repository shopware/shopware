<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\DraftLayoutDecoder;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
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
