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
        $this->tempDir = sys_get_temp_dir() . '/yaml_type_loader_test_' . bin2hex(random_bytes(8));
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

    #[TestDox('loads multiple YAML files from directory')]
    public function testLoadsMultipleFiles(): void
    {
        file_put_contents($this->tempDir . '/text.yaml', "meta:\n  name: \"Sw:Content:Text\"\n  label: \"Text\"\n  description: \"Text.\"\n  vendor: \"shopware AG\"");
        file_put_contents($this->tempDir . '/image.yaml', "meta:\n  name: \"Sw:Media:Image\"\n  label: \"Image\"\n  description: \"Image.\"\n  vendor: \"shopware AG\"");

        $loader = $this->createLoader();
        $definitions = $loader->load();

        static::assertCount(2, $definitions);
    }

    #[TestDox('loads full definition with properties and slots')]
    public function testLoadsFullDefinitionWithPropertiesAndSlots(): void
    {
        file_put_contents($this->tempDir . '/card.yaml', <<<'YAML'
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
YAML);

        $loader = $this->createLoader();
        $definitions = $loader->load();

        static::assertCount(1, $definitions);
        $def = $definitions[0];
        static::assertSame('Sw:Product:Card', $def->name());

        $schema = $def->toSchema();
        static::assertSame('Product Card', $schema['label']);
        static::assertSame('A product card.', $schema['description']);
        static::assertSame('shopware AG', $schema['vendor']);
        static::assertSame('card', $schema['icon']);
        static::assertSame('commerce', $schema['category']);
        static::assertSame('Card element', $schema['copilot']['summary']);
        static::assertCount(2, $schema['properties']);
        static::assertSame('Shopware\Core\Content\Product\ProductEntity', $schema['properties']['product']['type']);
        static::assertTrue($schema['properties']['product']['required']);
        static::assertSame(['box', 'list'], $schema['properties']['layout']['enum']);
        static::assertSame('box', $schema['properties']['layout']['default']);
        static::assertCount(2, $schema['slots']);
        static::assertSame('media', $schema['slots'][0]['name']);
        static::assertSame(1, $schema['slots'][0]['maxElements']);
        static::assertSame('Media slot.', $schema['slots'][0]['description']);
        static::assertSame('actions', $schema['slots'][1]['name']);
        static::assertSame(['Sw:Action:Button'], $schema['slots'][1]['allowList']);
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

    #[TestDox('throws for invalid YAML syntax')]
    public function testThrowsForInvalidYamlSyntax(): void
    {
        file_put_contents($this->tempDir . '/bad.yaml', "meta:\n  name: \"Sw:Bad:Yaml\n  broken: [");

        $loader = $this->createLoader();

        $this->expectException(ContentSystemException::class);
        $this->expectExceptionMessage('Failed to load element type from');
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
