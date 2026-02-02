<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityMetadata;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldMetadata;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityMetadata::class)]
final class EntityMetadataTest extends TestCase
{
    public function testConstruction(): void
    {
        $metadata = new EntityMetadata(
            entityName: 'test_entity',
            entityClass: Entity::class,
            collectionClass: EntityCollection::class,
            hydratorClass: EntityHydrator::class,
            fields: [],
            since: '6.6.0.0',
            parent: null,
        );

        static::assertSame('test_entity', $metadata->entityName);
        static::assertSame(Entity::class, $metadata->entityClass);
        static::assertSame(EntityCollection::class, $metadata->collectionClass);
        static::assertSame(EntityHydrator::class, $metadata->hydratorClass);
        static::assertSame([], $metadata->fields);
        static::assertSame('6.6.0.0', $metadata->since);
        static::assertNull($metadata->parent);
    }

    /**
     * @param list<FieldMetadata> $fields
     */
    #[DataProvider('hasTranslationProvider')]
    public function testHasTranslation(array $fields, bool $expected): void
    {
        $metadata = new EntityMetadata(
            entityName: 'test_entity',
            entityClass: Entity::class,
            collectionClass: EntityCollection::class,
            hydratorClass: EntityHydrator::class,
            fields: $fields,
        );

        static::assertSame($expected, $metadata->hasTranslation());
    }

    /**
     * @return \Generator<string, array{fields: list<FieldMetadata>, expected: bool}>
     */
    public static function hasTranslationProvider(): \Generator
    {
        yield 'no fields' => [
            'fields' => [],
            'expected' => false,
        ];

        yield 'non-translated field' => [
            'fields' => [
                new FieldMetadata(
                    fieldClass: StringField::class,
                    propertyName: 'name',
                    attribute: new Field(FieldType::STRING, translated: false),
                    entityName: 'test_entity',
                ),
            ],
            'expected' => false,
        ];

        yield 'translated field' => [
            'fields' => [
                new FieldMetadata(
                    fieldClass: StringField::class,
                    propertyName: 'name',
                    attribute: new Field(FieldType::STRING, translated: true),
                    entityName: 'test_entity',
                ),
            ],
            'expected' => true,
        ];

        yield 'mixed fields with one translated' => [
            'fields' => [
                new FieldMetadata(
                    fieldClass: StringField::class,
                    propertyName: 'code',
                    attribute: new Field(FieldType::STRING, translated: false),
                    entityName: 'test_entity',
                ),
                new FieldMetadata(
                    fieldClass: StringField::class,
                    propertyName: 'name',
                    attribute: new Field(FieldType::STRING, translated: true),
                    entityName: 'test_entity',
                ),
            ],
            'expected' => true,
        ];
    }

    public function testToDefinition(): void
    {
        $attribute = new Field(FieldType::STRING);
        $fieldMetadata = new FieldMetadata(
            fieldClass: StringField::class,
            propertyName: 'name',
            attribute: $attribute,
            entityName: 'test_entity',
        );

        $metadata = new EntityMetadata(
            entityName: 'test_entity',
            entityClass: Entity::class,
            collectionClass: EntityCollection::class,
            hydratorClass: EntityHydrator::class,
            fields: [$fieldMetadata],
            since: '6.6.0.0',
            parent: 'parent_entity',
        );

        $definition = $metadata->toDefinition();

        static::assertSame(EntityMetadata::class, $definition->getClass());

        $args = $definition->getArguments();
        static::assertCount(7, $args);
        static::assertSame('test_entity', $args[0]);
        static::assertSame(Entity::class, $args[1]);
        static::assertSame(EntityCollection::class, $args[2]);
        static::assertSame(EntityHydrator::class, $args[3]);
        static::assertIsArray($args[4]);
        static::assertCount(1, $args[4]);
        static::assertInstanceOf(Definition::class, $args[4][0]);
        static::assertSame(FieldMetadata::class, $args[4][0]->getClass());
        static::assertSame('6.6.0.0', $args[5]);
        static::assertSame('parent_entity', $args[6]);
    }
}
