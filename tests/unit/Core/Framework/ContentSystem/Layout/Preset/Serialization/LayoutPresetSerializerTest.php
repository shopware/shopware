<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LayoutPresetSerializer::class)]
class LayoutPresetSerializerTest extends TestCase
{
    #[TestDox('denormalizes a valid preset, compiling the layout into the payload')]
    public function testDenormalizeCompilesLayout(): void
    {
        $payload = [['id' => 'el-1', 'component' => 'Sw:Content:Text', 'properties' => []]];

        $compiler = $this->createMock(LayoutPresetPayloadCompiler::class);
        $compiler->expects($this->once())->method('compile')->with([['component' => 'Sw:Content:Text']])->willReturn($payload);

        $preset = (new LayoutPresetSerializer($compiler))->denormalize([
            'name' => 'Text block',
            'description' => 'A text block.',
            'icon' => 'regular-align-left',
            'layout' => [['component' => 'Sw:Content:Text']],
        ], 'Sw:TextBlock');

        static::assertSame('Sw:TextBlock', $preset->id);
        static::assertSame('Text block', $preset->name);
        static::assertSame('A text block.', $preset->description);
        static::assertSame('regular-align-left', $preset->icon);
        static::assertSame($payload, $preset->payload);
    }

    #[TestDox('leaves description and icon null when absent')]
    public function testDenormalizeKeepsNullMetadata(): void
    {
        $compiler = static::createStub(LayoutPresetPayloadCompiler::class);
        $compiler->method('compile')->willReturn([]);

        $preset = (new LayoutPresetSerializer($compiler))->denormalize([
            'name' => 'Empty',
            'layout' => [],
        ], 'Sw:Empty');

        static::assertNull($preset->description);
        static::assertNull($preset->icon);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[TestDox('rejects structurally invalid presets')]
    #[DataProvider('invalidProvider')]
    public function testValidateRejects(array $data): void
    {
        $serializer = new LayoutPresetSerializer(static::createStub(LayoutPresetPayloadCompiler::class));

        try {
            $serializer->validate($data);
            static::fail('Expected a ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESET_INVALID, $e->getErrorCode());
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'missing name' => [['layout' => []]];
        yield 'blank name' => [['name' => '', 'layout' => []]];
        yield 'non-string description' => [['name' => 'N', 'description' => 5, 'layout' => []]];
        yield 'non-string icon' => [['name' => 'N', 'icon' => 5, 'layout' => []]];
        yield 'missing layout' => [['name' => 'N']];
        yield 'non-list layout' => [['name' => 'N', 'layout' => ['not' => 'a list']]];
    }
}
