<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\ForeignKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldMetadata;
use Shopware\Core\Framework\DataAbstractionLayer\MappingMetadata;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingMetadata::class)]
final class MappingMetadataTest extends TestCase
{
    public function testConstruction(): void
    {
        $metadata = new MappingMetadata(
            entityName: 'product_category',
            fields: [],
            source: 'product',
            reference: 'category',
        );

        static::assertSame('product_category', $metadata->entityName);
        static::assertSame([], $metadata->fields);
        static::assertSame('product', $metadata->source);
        static::assertSame('category', $metadata->reference);
    }

    public function testConstructionWithFields(): void
    {
        $attribute = new ForeignKey(entity: 'product');
        $attribute->nullable = false;
        $fieldMetadata = new FieldMetadata(
            fieldClass: FkField::class,
            propertyName: 'productId',
            attribute: $attribute,
            entityName: 'product_category',
        );

        $metadata = new MappingMetadata(
            entityName: 'product_category',
            fields: [$fieldMetadata],
            source: 'product',
            reference: 'category',
        );

        static::assertCount(1, $metadata->fields);
        static::assertSame($fieldMetadata, $metadata->fields[0]);
    }

    /**
     * @param list<FieldMetadata> $fields
     */
    #[DataProvider('toDefinitionFieldCountProvider')]
    public function testToDefinition(array $fields, int $expectedFieldCount): void
    {
        $metadata = new MappingMetadata(
            entityName: 'product_category',
            fields: $fields,
            source: 'product',
            reference: 'category',
        );

        $definition = $metadata->toDefinition();

        static::assertSame(MappingMetadata::class, $definition->getClass());

        $args = $definition->getArguments();
        static::assertCount(4, $args);
        static::assertSame('product_category', $args[0]);
        static::assertIsArray($args[1]);
        static::assertCount($expectedFieldCount, $args[1]);

        foreach ($args[1] as $fieldDefinition) {
            static::assertInstanceOf(Definition::class, $fieldDefinition);
            static::assertSame(FieldMetadata::class, $fieldDefinition->getClass());
        }

        static::assertSame('product', $args[2]);
        static::assertSame('category', $args[3]);
    }

    /**
     * @return \Generator<string, array{fields: list<FieldMetadata>, expectedFieldCount: int}>
     */
    public static function toDefinitionFieldCountProvider(): \Generator
    {
        yield 'empty fields' => [
            'fields' => [],
            'expectedFieldCount' => 0,
        ];

        $productAttribute = new ForeignKey(entity: 'product');
        $productAttribute->nullable = false;

        yield 'single field' => [
            'fields' => [
                new FieldMetadata(
                    fieldClass: FkField::class,
                    propertyName: 'productId',
                    attribute: $productAttribute,
                    entityName: 'product_category',
                ),
            ],
            'expectedFieldCount' => 1,
        ];

        $categoryAttribute = new ForeignKey(entity: 'category');
        $categoryAttribute->nullable = false;

        yield 'multiple fields' => [
            'fields' => [
                new FieldMetadata(
                    fieldClass: FkField::class,
                    propertyName: 'productId',
                    attribute: $productAttribute,
                    entityName: 'product_category',
                ),
                new FieldMetadata(
                    fieldClass: FkField::class,
                    propertyName: 'categoryId',
                    attribute: $categoryAttribute,
                    entityName: 'product_category',
                ),
            ],
            'expectedFieldCount' => 2,
        ];
    }
}
