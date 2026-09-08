<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\LayoutPresetNameResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\LayoutPresetSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(YamlLayoutPresetLoader::class)]
class YamlLayoutPresetLoaderTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yaml-preset-loader-test-' . uniqid('', true);
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    #[TestDox('derives each preset id from its filename and the source prefix')]
    public function testLoadDerivesIdsFromFilenames(): void
    {
        $this->writePreset('text-block.yaml', ['name' => 'Text block', 'layout' => []]);
        $this->writePreset('media.yaml', ['name' => 'Media', 'layout' => []]);

        $loader = new YamlLayoutPresetLoader($this->serializer(), new LayoutPresetNameResolver(), [
            new LayoutPresetSourceDirectory('core', $this->tempDir, 'Sw'),
        ]);

        $ids = array_map(static fn (ContentSystemLayoutPresetSpecification $preset): string => $preset->id, $loader->load());
        sort($ids);

        static::assertSame(['Sw:Media', 'Sw:TextBlock'], $ids);
    }

    #[TestDox('returns an empty array for a directory that does not exist')]
    public function testMissingDirectoryReturnsEmpty(): void
    {
        $loader = new YamlLayoutPresetLoader($this->serializer(), new LayoutPresetNameResolver());

        static::assertSame([], $loader->loadFromDirectory($this->tempDir . '/nope', 'core', 'Sw'));
    }

    #[TestDox('reads the raw, metadata-validated presets keyed by derived id without compiling')]
    public function testReadRawFromDirectory(): void
    {
        $this->writePreset('text-block.yaml', ['name' => 'Text block', 'layout' => []]);

        $raw = (new YamlLayoutPresetLoader($this->serializer(), new LayoutPresetNameResolver()))
            ->readRawFromDirectory($this->tempDir, 'core', 'Sw');

        static::assertArrayHasKey('Sw:TextBlock', $raw);
        static::assertSame('Text block', $raw['Sw:TextBlock']['name']);
    }

    #[TestDox('throws when a yaml and yml file in the same directory resolve to the same id')]
    public function testDuplicateIdInDirectoryThrows(): void
    {
        $this->writePreset('text-block.yaml', ['name' => 'A', 'layout' => []]);
        $this->writePreset('text-block.yml', ['name' => 'B', 'layout' => []]);

        $this->expectExceptionObject(ContentSystemException::layoutPresetDuplicate('Sw:TextBlock'));
        (new YamlLayoutPresetLoader($this->serializer(), new LayoutPresetNameResolver()))
            ->loadFromDirectory($this->tempDir, 'core', 'Sw');
    }

    #[TestDox('rejects a filename that is not kebab-case')]
    public function testInvalidFilenameThrows(): void
    {
        $this->writePreset('Not_Kebab.yaml', ['name' => 'X', 'layout' => []]);

        try {
            (new YamlLayoutPresetLoader($this->serializer(), new LayoutPresetNameResolver()))
                ->loadFromDirectory($this->tempDir, 'core', 'Sw');
            static::fail('Expected a ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESET_INVALID_FILENAME, $e->getErrorCode());
        }
    }

    #[TestDox('fails hard on malformed YAML, naming the file')]
    public function testInvalidYamlThrows(): void
    {
        file_put_contents($this->tempDir . '/broken.yaml', "name: [unclosed\n");

        try {
            (new YamlLayoutPresetLoader($this->serializer(), new LayoutPresetNameResolver()))
                ->loadFromDirectory($this->tempDir, 'core', 'Sw');
            static::fail('Expected a ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::LAYOUT_PRESET_LOAD_FAILED, $e->getErrorCode());
        }
    }

    private function serializer(): LayoutPresetSerializer
    {
        $serializer = static::createStub(LayoutPresetSerializer::class);
        $serializer->method('denormalize')->willReturnCallback(
            static fn (array $data, string $id): ContentSystemLayoutPresetSpecification => new ContentSystemLayoutPresetSpecification($id, (string) $data['name'], null, null, [])
        );

        return $serializer;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writePreset(string $file, array $data): void
    {
        file_put_contents($this->tempDir . '/' . $file, Yaml::dump($data));
    }
}
