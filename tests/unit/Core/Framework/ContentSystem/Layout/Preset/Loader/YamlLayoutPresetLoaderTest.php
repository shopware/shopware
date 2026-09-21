<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Preset\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\LayoutPresetPayloadCompiler;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\LayoutPresetNameResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\LayoutPresetSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Loader\YamlLayoutPresetLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Serialization\LayoutPresetSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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

        $loader = $this->loader([new LayoutPresetSourceDirectory('core', $this->tempDir, 'Sw')]);

        $ids = array_map(static fn (ContentSystemLayoutPresetSpecification $preset): string => $preset->id, $loader->load());
        sort($ids);

        static::assertSame(['Sw:Media', 'Sw:TextBlock'], $ids);
    }

    #[TestDox('returns an empty array for a directory that does not exist')]
    public function testMissingDirectoryReturnsEmpty(): void
    {
        static::assertSame([], $this->loader()->loadFromDirectory($this->tempDir . '/nope', 'core', 'Sw'));
    }

    #[TestDox('loads the validated dtos keyed by derived id without compiling')]
    public function testLoadDtosFromDirectory(): void
    {
        $this->writePreset('text-block.yaml', ['name' => 'Text block', 'layout' => []]);

        $dtos = $this->loader()->loadDtosFromDirectory($this->tempDir, 'core', 'Sw');

        static::assertArrayHasKey('Sw:TextBlock', $dtos);
        static::assertSame('Text block', $dtos['Sw:TextBlock']->name);
    }

    #[TestDox('throws when a yaml and yml file in the same directory resolve to the same id')]
    public function testDuplicateIdInDirectoryThrows(): void
    {
        $this->writePreset('text-block.yaml', ['name' => 'A', 'layout' => []]);
        $this->writePreset('text-block.yml', ['name' => 'B', 'layout' => []]);

        $this->expectExceptionObject(ContentSystemException::layoutPresetDuplicate('Sw:TextBlock'));
        $this->loader()->loadFromDirectory($this->tempDir, 'core', 'Sw');
    }

    #[TestDox('rejects a filename that is not kebab-case')]
    public function testInvalidFilenameThrows(): void
    {
        $this->writePreset('Not_Kebab.yaml', ['name' => 'X', 'layout' => []]);

        $this->assertLoadFailsWith(ContentSystemException::LAYOUT_PRESET_INVALID_FILENAME);
    }

    #[TestDox('reports a validation failure across the directory collection')]
    public function testInvalidPresetThrows(): void
    {
        $this->writePreset('text-block.yaml', ['layout' => []]);

        $this->assertLoadFailsWith(ContentSystemException::LAYOUT_PRESETS_INVALID);
    }

    #[TestDox('rejects a layout that is not a list')]
    public function testNonListLayoutThrows(): void
    {
        $this->writePreset('text-block.yaml', ['name' => 'Text block', 'layout' => ['not' => 'a list']]);

        $this->assertLoadFailsWith(ContentSystemException::LAYOUT_PRESETS_INVALID);
    }

    #[TestDox('fails hard on malformed YAML, naming the file')]
    public function testInvalidYamlThrows(): void
    {
        file_put_contents($this->tempDir . '/broken.yaml', "name: [unclosed\n");

        $this->assertLoadFailsWith(ContentSystemException::LAYOUT_PRESET_LOAD_FAILED);
    }

    private function assertLoadFailsWith(string $errorCode): void
    {
        try {
            $this->loader()->loadFromDirectory($this->tempDir, 'core', 'Sw');
            static::fail('Expected a ContentSystemException.');
        } catch (ContentSystemException $e) {
            static::assertSame($errorCode, $e->getErrorCode());
        }
    }

    /**
     * @param list<LayoutPresetSourceDirectory> $directories
     */
    private function loader(array $directories = []): YamlLayoutPresetLoader
    {
        return new YamlLayoutPresetLoader(
            new LayoutPresetSpecificationSerializer(),
            static::createStub(LayoutPresetPayloadCompiler::class),
            $this->validator(),
            new LayoutPresetNameResolver(),
            $directories,
        );
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writePreset(string $file, array $data): void
    {
        file_put_contents($this->tempDir . '/' . $file, Yaml::dump($data + ['description' => 'A preset.', 'icon' => 'regular-circle']));
    }
}
