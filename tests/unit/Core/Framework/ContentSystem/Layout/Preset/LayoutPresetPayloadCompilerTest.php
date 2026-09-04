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

    #[TestDox('re-encodes the decoded elements into the served payload')]
    public function testCompileEncodesDecodedElements(): void
    {
        $decoder = $this->createStub(DraftLayoutDecoder::class);
        $decoder->method('decode')->willReturn([new StoredElement('el-1', 'Sw:Content:Text')]);

        $result = $this->createCompiler($decoder)->compile([['component' => 'Sw:Content:Text']]);

        static::assertSame([
            ['id' => 'el-1', 'component' => 'Sw:Content:Text', 'properties' => []],
        ], $result);
    }

    #[TestDox('an empty layout compiles to an empty payload')]
    public function testEmptyLayoutCompilesToEmptyPayload(): void
    {
        $decoder = $this->createStub(DraftLayoutDecoder::class);
        $decoder->method('decode')->willReturn([]);

        static::assertSame([], $this->createCompiler($decoder)->compile([]));
    }

    #[TestDox('throws when a node is not a mapping')]
    public function testNonArrayNodeThrows(): void
    {
        $this->assertInvalidLayout([['component' => 'Sw:Content:Text'], 'not-a-node']);
    }

    #[TestDox('throws when a node has no type')]
    public function testMissingTypeThrows(): void
    {
        $this->assertInvalidLayout([['properties' => ['text' => 'x']]]);
    }

    #[TestDox('throws when the type is blank')]
    public function testBlankTypeThrows(): void
    {
        $this->assertInvalidLayout([['component' => '']]);
    }

    #[TestDox('throws when properties is not a mapping')]
    public function testNonArrayPropertiesThrows(): void
    {
        $this->assertInvalidLayout([['component' => 'Sw:Content:Text', 'properties' => 'nope']]);
    }

    #[TestDox('throws when slots is not a mapping')]
    public function testNonArraySlotsThrows(): void
    {
        $this->assertInvalidLayout([['component' => 'Sw:Grid:Container', 'slots' => 'nope']]);
    }

    #[TestDox('throws when a slot does not map to a list of children')]
    public function testSlotChildrenNotListThrows(): void
    {
        $this->assertInvalidLayout([['component' => 'Sw:Grid:Container', 'slots' => ['content' => 'nope']]]);
    }

    /**
     * @param list<mixed> $layout
     */
    private function assertInvalidLayout(array $layout): void
    {
        try {
            $this->createCompiler($this->createStub(DraftLayoutDecoder::class))->compile($layout);
            static::fail('Expected a ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESET_INVALID_LAYOUT, $e->getErrorCode());
        }
    }

    /**
     * @param array<int, array<string, mixed>> $captured
     */
    private function capturingDecoder(array &$captured): DraftLayoutDecoder
    {
        $decoder = $this->createStub(DraftLayoutDecoder::class);
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
            new StoredElementCodec($this->createStub(DataLoaderConfigSerializerProvider::class)),
        );
    }
}
