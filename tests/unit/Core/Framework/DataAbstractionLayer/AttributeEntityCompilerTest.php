<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\AttributeEntityCompiler;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledDefinitions;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityMetadata;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited as InheritedFlag;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReverseInherited as ReverseInheritedFlag;
use Shopware\Core\Framework\DataAbstractionLayer\FieldMetadata;
use Shopware\Core\Framework\DataAbstractionLayer\FlagMetadata;
use Shopware\Core\Framework\DataAbstractionLayer\MappingMetadata;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntity;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityCollection;
use Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture\AttributeEntityWithInheritance;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AttributeEntityCompiler::class)]
#[CoversClass(CompiledDefinitions::class)]
final class AttributeEntityCompilerTest extends TestCase
{
    public function testCompileWithoutEntityAttribute(): void
    {
        $compiler = new AttributeEntityCompiler();

        $compiledResult = $compiler->compile(Entity::class);

        static::assertTrue($compiledResult->isEmpty());
        static::assertNull($compiledResult->entity);
        static::assertSame([], $compiledResult->mappings);
    }

    public function testCompile(): void
    {
        $compiler = new AttributeEntityCompiler();

        $compiledResult = $compiler->compile(AttributeEntity::class);

        static::assertFalse($compiledResult->isEmpty());

        $entity = $compiledResult->entity;
        static::assertInstanceOf(EntityMetadata::class, $entity);
        static::assertSame('attribute_entity', $entity->entityName);
        static::assertSame(AttributeEntity::class, $entity->entityClass);
        static::assertSame(AttributeEntityCollection::class, $entity->collectionClass);
        static::assertSame(EntityHydrator::class, $entity->hydratorClass);
        static::assertSame('6.6.3.0', $entity->since);
        static::assertNull($entity->parent);

        static::assertNotEmpty($entity->fields);
        static::assertContainsOnlyInstancesOf(FieldMetadata::class, $entity->fields);

        $fieldNames = array_map(fn (FieldMetadata $f) => $f->propertyName, $entity->fields);
        static::assertContains('id', $fieldNames);
        static::assertContains('string', $fieldNames);
        static::assertContains('currency', $fieldNames);
        static::assertContains('translations', $fieldNames);
        static::assertContains('customFields', $fieldNames);

        static::assertCount(3, $compiledResult->mappings);
        static::assertContainsOnlyInstancesOf(MappingMetadata::class, $compiledResult->mappings);

        $mappingNames = array_map(fn (MappingMetadata $m) => $m->entityName, $compiledResult->mappings);
        static::assertContains('attribute_entity_currency', $mappingNames);
        static::assertContains('attribute_entity_order', $mappingNames);
        static::assertContains('my_own_mapping_table_name', $mappingNames);

        $idField = array_values(array_filter($entity->fields, fn (FieldMetadata $f) => $f->propertyName === 'id'))[0];
        static::assertNotEmpty($idField->flags);
        static::assertContainsOnlyInstancesOf(FlagMetadata::class, $idField->flags);
    }

    public function testInheritedAttributeCompilesCorrectly(): void
    {
        $compiler = new AttributeEntityCompiler();

        $compiledResult = $compiler->compile(AttributeEntityWithInheritance::class);

        static::assertInstanceOf(EntityMetadata::class, $compiledResult->entity);
        static::assertSame('attribute_entity_inheritance', $compiledResult->entity->entityName);

        $entity = $compiledResult->entity;

        $findField = fn (string $name): ?FieldMetadata => array_values(
            array_filter($entity->fields, fn (FieldMetadata $f) => $f->propertyName === $name)
        )[0] ?? null;

        $hasFlag = fn (FieldMetadata $field, string $flagClass): bool => \count(
            array_filter($field->flags, fn (FlagMetadata $f) => $f->flagClass === $flagClass)
        ) > 0;

        $getFlagArgs = fn (FieldMetadata $field, string $flagClass): array => array_values(
            array_filter($field->flags, fn (FlagMetadata $f) => $f->flagClass === $flagClass)
        )[0]->args ?? [];

        $inheritedStringField = $findField('inheritedString');
        static::assertNotNull($inheritedStringField, 'inheritedString field not found');
        static::assertTrue($hasFlag($inheritedStringField, InheritedFlag::class), 'inheritedString should have Inherited flag');
        static::assertSame([null], $getFlagArgs($inheritedStringField, InheritedFlag::class));

        $inheritedCurrencyIdField = $findField('currencyId');
        static::assertNotNull($inheritedCurrencyIdField, 'currencyId field not found');
        static::assertTrue($hasFlag($inheritedCurrencyIdField, InheritedFlag::class), 'currencyId should have Inherited flag');

        $inheritedCurrencyField = $findField('currency');
        static::assertNotNull($inheritedCurrencyField, 'currency field not found');
        static::assertTrue($hasFlag($inheritedCurrencyField, InheritedFlag::class), 'currency should have Inherited flag');

        $inheritedWithForeignKeyField = $findField('inheritedWithForeignKey');
        static::assertNotNull($inheritedWithForeignKeyField, 'inheritedWithForeignKey field not found');
        static::assertTrue($hasFlag($inheritedWithForeignKeyField, InheritedFlag::class), 'inheritedWithForeignKey should have Inherited flag');
        static::assertSame(['custom_fk'], $getFlagArgs($inheritedWithForeignKeyField, InheritedFlag::class), 'foreignKey parameter should be passed through');

        $inheritedProductField = $findField('product');
        static::assertNotNull($inheritedProductField, 'product field not found');
        static::assertTrue($hasFlag($inheritedProductField, ReverseInheritedFlag::class), 'product should have ReverseInherited flag');
        static::assertSame(['attributed'], $getFlagArgs($inheritedProductField, ReverseInheritedFlag::class));
    }
}
