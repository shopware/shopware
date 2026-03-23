<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(YamlTypeLoader::class)]
class YamlTypeLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/yaml_type_loader_test_' . getmypid();
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    #[TestDox('loads multiple files from directory')]
    public function testLoadsMultipleFilesFromDirectory(): void
    {
        file_put_contents($this->tempDir . '/text.yaml', "meta:\n  name: \"Sw:Content:Text\"\n  label: \"Text\"\n  description: \"Text.\"\n  vendor: \"shopware AG\"");
        copy(__DIR__ . '/_fixtures/card.yaml', $this->tempDir . '/card.yaml');

        $loader = $this->createLoader();
        $definitions = $loader->load();

        static::assertCount(2, $definitions);
    }

    #[TestDox('loads and returns named specification from YAML file')]
    public function testLoadsNamedSpecificationFromYamlFile(): void
    {
        copy(__DIR__ . '/_fixtures/card.yaml', $this->tempDir . '/card.yaml');

        $loader = $this->createLoader();
        $definitions = $loader->load();

        static::assertCount(1, $definitions);
        static::assertSame('Sw:Product:Card', $definitions[0]->name());
    }

    #[TestDox('returns empty array for non-existent directory')]
    public function testReturnsEmptyArrayForNonExistentDirectory(): void
    {
        $loader = new YamlTypeLoader(
            new ElementTypeSpecificationSerializer(),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            '/nonexistent/path',
        );

        static::assertSame([], $loader->load());
    }

    #[TestDox('returns empty array for directory without YAML files')]
    public function testReturnsEmptyArrayForEmptyDirectory(): void
    {
        $loader = $this->createLoader();

        static::assertSame([], $loader->load());
    }

    #[TestDox('throws for invalid YAML syntax')]
    public function testThrowsForInvalidYamlSyntax(): void
    {
        file_put_contents($this->tempDir . '/bad.yaml', "meta:\n  name: \"Sw:Bad:Yaml\n  broken: [");

        $loader = $this->createLoader();

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Invalid YAML syntax');
        $loader->load();
    }

    #[TestDox('throws for YAML file that is not an array')]
    public function testThrowsWhenYamlFileIsNotArray(): void
    {
        file_put_contents($this->tempDir . '/scalar.yaml', 'just a string');

        $loader = $this->createLoader();

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('YAML file must contain an array');
        $loader->load();
    }

    #[TestDox('throws for duplicate names within same directory')]
    public function testThrowsForDuplicateTypeNames(): void
    {
        file_put_contents($this->tempDir . '/text1.yaml', "meta:\n  name: \"Sw:Content:Text\"\n  label: \"Text 1\"\n  description: \"Text.\"\n  vendor: \"shopware AG\"");
        file_put_contents($this->tempDir . '/text2.yaml', "meta:\n  name: \"Sw:Content:Text\"\n  label: \"Text 2\"\n  description: \"Text.\"\n  vendor: \"shopware AG\"");

        $loader = $this->createLoader();

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Content:Text', 'text1.yaml', 'text2.yaml')
        );
        $loader->load();
    }

    private function createLoader(): YamlTypeLoader
    {
        return new YamlTypeLoader(
            new ElementTypeSpecificationSerializer(),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
            $this->tempDir,
        );
    }
}
