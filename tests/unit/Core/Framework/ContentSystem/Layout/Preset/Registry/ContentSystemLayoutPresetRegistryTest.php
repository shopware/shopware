<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Registry\ContentSystemLayoutPresetRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemLayoutPresetRegistry::class)]
class ContentSystemLayoutPresetRegistryTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/preset-registry-test-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    #[TestDox('loads presets keyed by id, with metadata and the compiled payload')]
    public function testAllLoadsPresetsKeyedById(): void
    {
        $this->writePreset('text-block.yaml', [
            'id' => 'core.text-block',
            'name' => 'Text block',
            'description' => 'A single text element.',
            'icon' => 'regular-align-left',
            'layout' => [['component' => 'Sw:Content:Text']],
        ]);
        $this->writePreset('other.yaml', [
            'id' => 'core.media-and-text',
            'name' => 'Media & text',
            'layout' => [['component' => 'Sw:Grid:Container']],
        ]);

        $payload = [['id' => 'el-1', 'component' => 'Sw:Content:Text', 'properties' => []]];
        $compiler = $this->createStub(LayoutPresetPayloadCompiler::class);
        $compiler->method('compile')->willReturn($payload);

        $all = $this->createRegistry($compiler)->all();

        static::assertCount(2, $all);
        static::assertArrayHasKey('core.text-block', $all);
        static::assertArrayHasKey('core.media-and-text', $all);

        $preset = $all['core.text-block'];
        static::assertSame('Text block', $preset->name);
        static::assertSame('A single text element.', $preset->description);
        static::assertSame('regular-align-left', $preset->icon);
        static::assertSame($payload, $preset->payload);

        static::assertNull($all['core.media-and-text']->description);
        static::assertNull($all['core.media-and-text']->icon);
    }

    #[TestDox('returns an empty array when the directory does not exist')]
    public function testAllReturnsEmptyWhenDirectoryMissing(): void
    {
        $registry = new ContentSystemLayoutPresetRegistry(
            $this->createStub(LayoutPresetPayloadCompiler::class),
            $this->tempDir . '/does-not-exist',
        );

        static::assertSame([], $registry->all());
    }

    #[TestDox('compiles the authoring shorthand through the payload compiler')]
    public function testAllCompilesLayoutThroughCompiler(): void
    {
        $layout = [['component' => 'Sw:Content:Text', 'properties' => ['text' => 'x']]];
        $this->writePreset('text-block.yaml', [
            'id' => 'core.text-block',
            'name' => 'Text block',
            'layout' => $layout,
        ]);

        $compiler = $this->createMock(LayoutPresetPayloadCompiler::class);
        $compiler->expects($this->once())->method('compile')->with($layout)->willReturn([]);

        (new ContentSystemLayoutPresetRegistry($compiler, $this->tempDir))->all();
    }

    #[TestDox('returns true for a known id and false for an unknown one')]
    public function testHas(): void
    {
        $this->writePreset('text-block.yaml', ['id' => 'core.text-block', 'name' => 'Text block', 'layout' => []]);

        $registry = $this->createRegistry();

        static::assertTrue($registry->has('core.text-block'));
        static::assertFalse($registry->has('core.unknown'));
    }

    #[TestDox('returns the preset for a known id')]
    public function testGetReturnsPreset(): void
    {
        $this->writePreset('text-block.yaml', ['id' => 'core.text-block', 'name' => 'Text block', 'layout' => []]);

        static::assertSame('Text block', $this->createRegistry()->get('core.text-block')->name);
    }

    #[TestDox('throws for an unknown id on get')]
    public function testGetThrowsForUnknownId(): void
    {
        $this->expectExceptionObject(ContentSystemException::layoutPresetNotFound('core.unknown'));
        $this->createRegistry()->get('core.unknown');
    }

    #[TestDox('throws DecorationPatternException when calling getDecorated')]
    public function testGetDecoratedThrows(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(ContentSystemLayoutPresetRegistry::class));
        $this->createRegistry()->getDecorated();
    }

    #[TestDox('throws when two presets declare the same id')]
    public function testDuplicateIdThrows(): void
    {
        $this->writePreset('a.yaml', ['id' => 'core.dupe', 'name' => 'A', 'layout' => []]);
        $this->writePreset('b.yaml', ['id' => 'core.dupe', 'name' => 'B', 'layout' => []]);

        $this->expectExceptionObject(ContentSystemException::layoutPresetDuplicate('core.dupe'));
        $this->createRegistry()->all();
    }

    #[TestDox('fails hard on malformed YAML')]
    public function testInvalidYamlThrows(): void
    {
        file_put_contents($this->tempDir . '/broken.yaml', "id: [unclosed\n");

        $this->assertLoadFailed();
    }

    #[TestDox('fails hard when the id is missing')]
    public function testMissingIdThrows(): void
    {
        $this->writePreset('no-id.yaml', ['name' => 'Nameless', 'layout' => []]);

        $this->assertLoadFailed();
    }

    #[TestDox('fails hard when the name is missing')]
    public function testMissingNameThrows(): void
    {
        $this->writePreset('no-name.yaml', ['id' => 'core.no-name', 'layout' => []]);

        $this->assertLoadFailed();
    }

    #[TestDox('fails hard when the layout is not a list')]
    public function testNonListLayoutThrows(): void
    {
        $this->writePreset('bad-layout.yaml', ['id' => 'core.bad', 'name' => 'Bad', 'layout' => ['not' => 'a list']]);

        $this->assertLoadFailed();
    }

    #[TestDox('wraps a compiler rejection as a load failure')]
    public function testCompilerRejectionWrappedAsLoadFailed(): void
    {
        $this->writePreset('text-block.yaml', [
            'id' => 'core.text-block',
            'name' => 'Text block',
            'layout' => [['component' => 'Sw:Content:Text']],
        ]);

        $compiler = $this->createStub(LayoutPresetPayloadCompiler::class);
        $compiler->method('compile')->willThrowException(ContentSystemException::layoutPresetInvalidLayout('nope'));

        try {
            (new ContentSystemLayoutPresetRegistry($compiler, $this->tempDir))->all();
            static::fail('Expected a ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESET_LOAD_FAILED, $e->getErrorCode());
        }
    }

    private function createRegistry(?LayoutPresetPayloadCompiler $compiler = null): ContentSystemLayoutPresetRegistry
    {
        return new ContentSystemLayoutPresetRegistry(
            $compiler ?? $this->createStub(LayoutPresetPayloadCompiler::class),
            $this->tempDir,
        );
    }

    private function assertLoadFailed(): void
    {
        try {
            $this->createRegistry()->all();
            static::fail('Expected a ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESET_LOAD_FAILED, $e->getErrorCode());
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writePreset(string $file, array $data): void
    {
        file_put_contents($this->tempDir . '/' . $file, Yaml::dump($data));
    }
}
