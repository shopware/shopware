<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Loader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\YamlTypeLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Serialization\ElementTypeSpecificationSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Util\Filesystem;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(YamlTypeLoader::class)]
class YamlTypeLoaderTest extends TestCase
{
    private const CARD_YAML = <<<'YAML'
meta:
  name: "Sw:Product:Card"
  label: "Product Card"
  description: "A product card."
  vendor: "shopware AG"
  icon: "card"
  category: "commerce"
  copilot:
    summary: "Card element"
    hints:
      - "Use for products"
properties:
  product:
    type: Shopware\Core\Content\Product\ProductEntity
    required: true
    title: "Product"
    description: "The product."
  layout:
    type: string
    enum: ["box", "list"]
    default: "box"
    title: "Layout"
    description: "Layout variant."
slots:
  - name: media
    maxElements: 1
    description: "Media slot."
  - name: actions
    allowList:
      - "Sw:Action:Button"
YAML;

    private const TEXT_YAML = <<<'YAML'
meta:
  name: "Sw:Content:Text"
  label: "Text"
  description: "Text."
  vendor: "shopware AG"
YAML;

    #[TestDox('loads multiple files from directory')]
    public function testLoadsMultipleFilesFromDirectory(): void
    {
        $filesystem = $this->createFilesystemMock([
            'text.yaml' => self::TEXT_YAML,
            'card.yaml' => self::CARD_YAML,
        ]);

        $loader = $this->createLoader();
        $definitions = $loader->load($filesystem);

        static::assertCount(2, $definitions);

        $names = array_map(static fn (ContentSystemElementTypeSpecification $d) => $d->name(), $definitions);
        static::assertContains('Sw:Content:Text', $names);
        static::assertContains('Sw:Product:Card', $names);
    }

    #[TestDox('loads and returns named specification from YAML file')]
    public function testLoadsNamedSpecificationFromYamlFile(): void
    {
        $filesystem = $this->createFilesystemMock([
            'card.yaml' => self::CARD_YAML,
        ]);

        $loader = $this->createLoader();
        $definitions = $loader->load($filesystem);

        static::assertCount(1, $definitions);
        static::assertSame('Sw:Product:Card', $definitions[0]->name());
    }

    #[TestDox('returns empty array for non-existent directory')]
    public function testReturnsEmptyArrayForNonExistentDirectory(): void
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('has')->willReturn(false);

        $loader = $this->createLoader();

        static::assertSame([], $loader->load($filesystem));
    }

    #[TestDox('returns empty array for directory without YAML files')]
    public function testReturnsEmptyArrayForEmptyDirectory(): void
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('has')->willReturn(true);
        $filesystem->method('findFiles')->willReturn([]);

        $loader = $this->createLoader();

        static::assertSame([], $loader->load($filesystem));
    }

    #[TestDox('throws for invalid YAML syntax')]
    public function testThrowsForInvalidYamlSyntax(): void
    {
        $filesystem = $this->createFilesystemMock([
            'bad.yaml' => "meta:\n  name: \"Sw:Bad:Yaml\n  broken: [",
        ]);

        $loader = $this->createLoader();

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Invalid YAML syntax');
        $loader->load($filesystem);
    }

    #[TestDox('throws for YAML file that is not an array')]
    public function testThrowsWhenYamlFileIsNotArray(): void
    {
        $filesystem = $this->createFilesystemMock([
            'scalar.yaml' => 'just a string',
        ]);

        $loader = $this->createLoader();

        $this->expectExceptionObject(
            ContentSystemException::elementTypeLoadFailed('/fake/scalar.yaml', 'YAML file must contain an array/map, got string')
        );
        $loader->load($filesystem);
    }

    #[TestDox('throws for YAML file with validation violations')]
    public function testThrowsForValidationViolations(): void
    {
        $filesystem = $this->createFilesystemMock([
            'invalid.yaml' => "meta:\n  name: \"\"\n  label: \"\"\n  description: \"\"\n  vendor: \"\"",
        ]);

        $loader = $this->createLoader();

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Element type "<unknown>" is invalid');
        $loader->load($filesystem);
    }

    #[TestDox('throws for duplicate names within same directory')]
    public function testThrowsForDuplicateTypeNames(): void
    {
        $filesystem = $this->createFilesystemMock([
            'text1.yaml' => self::TEXT_YAML,
            'text2.yaml' => self::TEXT_YAML,
        ]);

        $loader = $this->createLoader();

        $this->expectExceptionObject(
            ContentSystemException::elementTypeDuplicate('Sw:Content:Text', 'text1.yaml', 'text2.yaml')
        );
        $loader->load($filesystem);
    }

    /**
     * @param array<string, string> $files filename => YAML content
     */
    private function createFilesystemMock(array $files): Filesystem
    {
        $splFiles = [];
        foreach ($files as $filename => $content) {
            $splFiles[] = new SplFileInfo('/fake/' . $filename, '', $filename);
        }

        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('has')->willReturn(true);
        $filesystem->method('findFiles')->willReturn($splFiles);

        $readMap = [];
        foreach ($files as $filename => $content) {
            $readMap[] = [$filename, $content];
        }
        $filesystem->method('read')->willReturnMap($readMap);

        $filesystem->method('path')->willReturnCallback(
            static fn (string ...$path): string => '/fake/' . implode('/', $path)
        );

        return $filesystem;
    }

    private function createLoader(): YamlTypeLoader
    {
        return new YamlTypeLoader(
            new ElementTypeSpecificationSerializer(),
            Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator(),
        );
    }
}
