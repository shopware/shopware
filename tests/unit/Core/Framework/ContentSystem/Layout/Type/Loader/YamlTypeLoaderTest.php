<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeNameResolver;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeSourceDirectory;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(YamlTypeLoader::class)]
class YamlTypeLoaderTest extends TestCase
{
    private const BUNDLE_A_TYPES_DIR = __DIR__ . '/fixtures/bundle-a/Resources/content-system/types';

    private const PLUGIN_TYPES_DIR = __DIR__ . '/fixtures/test-plugin/Resources/content-system/types';

    private const MINIMAL_VALID_YAML = "meta:\n  label: \"Test\"\n  description: \"Test.\"\n  vendor: \"shopware AG\"\n";

    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yaml-type-loader-test-' . getmypid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->tempDir);
    }

    #[TestDox('returns specifications from all configured directories with their source labels')]
    public function testLoadsSpecificationsFromInjectedDirectories(): void
    {
        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('bundle-a', self::BUNDLE_A_TYPES_DIR, 'Sw'),
            new ElementTypeSourceDirectory('test-plugin', self::PLUGIN_TYPES_DIR, 'TestPlugin'),
        ]);

        $definitions = $loader->load();

        static::assertCount(2, $definitions);

        $byName = [];
        foreach ($definitions as $spec) {
            $byName[$spec->name()] = $spec;
        }

        static::assertArrayHasKey('Sw:Test:Element', $byName);
        static::assertArrayHasKey('TestPlugin:Plugin:Element', $byName);
    }

    #[TestDox('returns resolved DTOs with correct name and source when loading from a single directory')]
    public function testLoadsDtosFromSingleDirectory(): void
    {
        $loader = $this->createLoader([]);

        $dtos = $loader->loadDtosFromDirectory(self::BUNDLE_A_TYPES_DIR, 'test-source', 'Sw');

        static::assertCount(1, $dtos);
        static::assertSame('Sw:Test:Element', $dtos[0]->name);
        static::assertSame('test-source', $dtos[0]->source);
    }

    #[TestDox('returns specifications with correct name and source when loading from a single directory')]
    public function testLoadsSpecificationsFromSingleDirectory(): void
    {
        $loader = $this->createLoader([]);

        $definitions = $loader->loadFromDirectory(self::BUNDLE_A_TYPES_DIR, 'test-source', 'Sw');

        static::assertCount(1, $definitions);
        static::assertSame('Sw:Test:Element', $definitions[0]->name());
        static::assertSame('test-source', $definitions[0]->source());
    }

    #[TestDox('returns empty array when no directories are injected')]
    public function testReturnsEmptyArrayWhenNoDirectoriesInjected(): void
    {
        $loader = $this->createLoader([]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns empty array for non-existent directory path')]
    public function testReturnsEmptyArrayForNonExistentDirectory(): void
    {
        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('missing', '/path/that/does/not/exist', 'X'),
        ]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns empty array for directory without matching files')]
    public function testReturnsEmptyArrayForDirectoryWithoutYamlFiles(): void
    {
        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('empty', $this->tempDir, 'X'),
        ]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('throws duplicate exception when the same type name exists in two different directories')]
    public function testThrowsForCrossDirectoryDuplicateTypeName(): void
    {
        $dirA = $this->tempDir . '/dir-a';
        $dirB = $this->tempDir . '/dir-b';
        mkdir($dirA, 0777, true);
        mkdir($dirB, 0777, true);

        file_put_contents($dirA . '/dup.yaml', self::MINIMAL_VALID_YAML);
        file_put_contents($dirB . '/dup.yaml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source-a', $dirA, 'Sw'),
            new ElementTypeSourceDirectory('source-b', $dirB, 'Sw'),
        ]);

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Dup', 'source-a', 'source-b')
        );
        $loader->load();
    }

    #[TestDox('throws duplicate exception when yaml and yml files resolve to the same type name')]
    public function testThrowsForDuplicateTypeNamesInSameDirectory(): void
    {
        file_put_contents($this->tempDir . '/button.yaml', self::MINIMAL_VALID_YAML);
        file_put_contents($this->tempDir . '/button.yml', self::MINIMAL_VALID_YAML);

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source', $this->tempDir, 'Sw'),
        ]);

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Button', 'button.yaml', 'button.yml')
        );
        $loader->load();
    }

    #[TestDox('throws when file contains unparsable content')]
    public function testThrowsForUnparsableFileContent(): void
    {
        file_put_contents($this->tempDir . '/bad.yaml', "meta:\n  label: \"Bad\n  broken: [");

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source', $this->tempDir, 'Sw'),
        ]);

        // Parse message varies by Symfony version, so assert file path + error prefix
        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage($this->tempDir . '/bad.yaml');
        $loader->load();
    }

    #[TestDox('throws when file content is scalar instead of a map')]
    public function testThrowsWhenFileContentIsScalar(): void
    {
        file_put_contents($this->tempDir . '/scalar.yaml', 'just a string');

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source', $this->tempDir, 'Sw'),
        ]);

        $this->expectExceptionObject(
            ContentSystemException::elementTypeLoadFailed(
                $this->tempDir . '/scalar.yaml',
                'YAML file must contain an array/map, got string'
            )
        );
        $loader->load();
    }

    #[TestDox('throws batch validation exception when files have violations')]
    public function testThrowsForValidationViolations(): void
    {
        $yaml = "meta:\n  label: \"\"\n  description: \"\"\n  vendor: \"\"";
        file_put_contents($this->tempDir . '/invalid.yaml', $yaml);

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source', $this->tempDir, 'Sw'),
        ]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('[Sw:Invalid].label');
        $loader->load();
    }

    #[TestDox('batch validation reports violations from multiple invalid files')]
    public function testBatchValidationReportsMultipleFiles(): void
    {
        $yaml = "meta:\n  label: \"\"\n  description: \"\"\n  vendor: \"\"";
        mkdir($this->tempDir . '/a', 0777, true);
        mkdir($this->tempDir . '/b', 0777, true);
        file_put_contents($this->tempDir . '/a/invalid-a.yaml', $yaml);
        file_put_contents($this->tempDir . '/b/invalid-b.yaml', $yaml);

        $loader = $this->createLoader([
            new ElementTypeSourceDirectory('source', $this->tempDir, 'Sw'),
        ]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/\[Sw:A:InvalidA\].*\[Sw:B:InvalidB\]/s');
        $loader->load();
    }

    /**
     * @param list<ElementTypeSourceDirectory> $directories
     */
    private function createLoader(array $directories): YamlTypeLoader
    {
        return new YamlTypeLoader(
            new ElementTypeSpecificationSerializer(),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            new ElementTypeNameResolver(),
            $directories,
        );
    }
}
