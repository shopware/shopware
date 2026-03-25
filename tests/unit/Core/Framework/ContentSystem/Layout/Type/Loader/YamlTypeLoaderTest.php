<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
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

    #[TestDox('loads specifications from injected directories with correct source labels')]
    public function testLoadsSpecificationsFromInjectedDirectories(): void
    {
        $loader = $this->createLoader([
            'bundle-a' => self::BUNDLE_A_TYPES_DIR,
            'test-plugin' => self::PLUGIN_TYPES_DIR,
        ]);

        $definitions = $loader->load();

        static::assertCount(2, $definitions);

        $byName = [];
        foreach ($definitions as $spec) {
            $byName[$spec->name()] = $spec;
        }

        static::assertArrayHasKey('Sw:Test:Element', $byName);
        static::assertArrayHasKey('Sw:Plugin:Element', $byName);
        static::assertSame('bundle-a', $byName['Sw:Test:Element']->source());
        static::assertSame('test-plugin', $byName['Sw:Plugin:Element']->source());
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
            'missing' => '/path/that/does/not/exist',
        ]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns empty array for directory without YAML files')]
    public function testReturnsEmptyArrayForDirectoryWithoutYamlFiles(): void
    {
        $loader = $this->createLoader([
            'empty' => $this->tempDir,
        ]);

        static::assertSame([], $loader->load());
    }

    #[TestDox('throws for cross-directory duplicate type name')]
    public function testThrowsForCrossDirectoryDuplicateTypeName(): void
    {
        $dirA = $this->tempDir . '/dir-a';
        $dirB = $this->tempDir . '/dir-b';
        mkdir($dirA, 0777, true);
        mkdir($dirB, 0777, true);

        $yaml = "meta:\n  name: \"Sw:Duplicate:Element\"\n  label: \"Dup\"\n  description: \"Dup.\"\n  vendor: \"shopware AG\"\n";
        file_put_contents($dirA . '/dup.yaml', $yaml);
        file_put_contents($dirB . '/dup.yaml', $yaml);

        $loader = $this->createLoader([
            'source-a' => $dirA,
            'source-b' => $dirB,
        ]);

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Duplicate:Element', 'source-a', 'source-b')
        );
        $loader->load();
    }

    #[TestDox('throws for duplicate type names within the same directory')]
    public function testThrowsForWithinDirectoryDuplicateTypeName(): void
    {
        $yaml = "meta:\n  name: \"Sw:Same:Element\"\n  label: \"Same\"\n  description: \"Same.\"\n  vendor: \"shopware AG\"\n";
        file_put_contents($this->tempDir . '/a-same.yaml', $yaml);
        file_put_contents($this->tempDir . '/b-same.yaml', $yaml);

        $loader = $this->createLoader([
            'source' => $this->tempDir,
        ]);

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Same:Element', 'a-same.yaml', 'b-same.yaml')
        );
        $loader->load();
    }

    #[TestDox('throws for invalid YAML syntax')]
    public function testThrowsForInvalidYamlSyntax(): void
    {
        file_put_contents($this->tempDir . '/bad.yaml', "meta:\n  name: \"Sw:Bad:Yaml\n  broken: [");

        $loader = $this->createLoader([
            'source' => $this->tempDir,
        ]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/bad\.yaml.*Invalid YAML syntax/s');
        $loader->load();
    }

    #[TestDox('throws for YAML file that is not an array')]
    public function testThrowsWhenYamlFileIsNotArray(): void
    {
        file_put_contents($this->tempDir . '/scalar.yaml', 'just a string');

        $loader = $this->createLoader([
            'source' => $this->tempDir,
        ]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/scalar\.yaml.*YAML file must contain an array\/map, got string/s');
        $loader->load();
    }

    #[TestDox('throws for YAML file with validation violations')]
    public function testThrowsForValidationViolations(): void
    {
        file_put_contents($this->tempDir . '/invalid.yaml', "meta:\n  name: \"\"\n  label: \"\"\n  description: \"\"\n  vendor: \"\"");

        $loader = $this->createLoader([
            'source' => $this->tempDir,
        ]);

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessageMatches('/Element type "<unknown>" is invalid.*name/s');
        $loader->load();
    }

    /**
     * @param array<string, string> $directories source label => absolute path
     */
    private function createLoader(array $directories): YamlTypeLoader
    {
        return new YamlTypeLoader(
            new ElementTypeSpecificationSerializer(),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            $directories,
        );
    }
}
