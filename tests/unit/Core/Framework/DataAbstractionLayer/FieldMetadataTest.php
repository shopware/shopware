<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldMetadata;
use Shopware\Core\Framework\DataAbstractionLayer\FlagMetadata;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FieldMetadata::class)]
final class FieldMetadataTest extends TestCase
{
    public function testConstruction(): void
    {
        $attribute = new Field(FieldType::STRING);
        $metadata = new FieldMetadata(
            fieldClass: StringField::class,
            propertyName: 'name',
            attribute: $attribute,
            entityName: 'test_entity',
        );

        static::assertSame(StringField::class, $metadata->fieldClass);
        static::assertSame('name', $metadata->propertyName);
        static::assertSame($attribute, $metadata->attribute);
        static::assertSame('test_entity', $metadata->entityName);
        static::assertSame([], $metadata->flags);
        static::assertNull($metadata->propertyType);
    }

    public function testConstructionWithFlags(): void
    {
        $attribute = new Field(FieldType::STRING);
        $flagMetadata = new FlagMetadata(Required::class);

        $metadata = new FieldMetadata(
            fieldClass: StringField::class,
            propertyName: 'name',
            attribute: $attribute,
            entityName: 'test_entity',
            flags: [$flagMetadata],
        );

        static::assertCount(1, $metadata->flags);
        static::assertSame($flagMetadata, $metadata->flags[0]);
    }

    public function testConstructionWithPropertyType(): void
    {
        $attribute = new Field(FieldType::ENUM);

        $metadata = new FieldMetadata(
            fieldClass: StringField::class,
            propertyName: 'status',
            attribute: $attribute,
            entityName: 'test_entity',
            flags: [],
            propertyType: 'App\\Enum\\Status',
        );

        static::assertSame('App\\Enum\\Status', $metadata->propertyType);
    }

    public function testInvalidFieldClassThrowsException(): void
    {
        $this->expectException(DataAbstractionLayerException::class);
        $this->expectExceptionMessage('FieldMetadata requires a Field subclass, got "InvalidClass".');

        $attribute = new Field(FieldType::STRING);
        new FieldMetadata(
            fieldClass: 'InvalidClass', // @phpstan-ignore argument.type
            propertyName: 'name',
            attribute: $attribute,
            entityName: 'test_entity',
        );
    }

    public function testToDefinition(): void
    {
        $attribute = new Field(FieldType::STRING, api: true, column: 'custom_name');
        $flagMetadata = new FlagMetadata(Required::class);

        $metadata = new FieldMetadata(
            fieldClass: StringField::class,
            propertyName: 'name',
            attribute: $attribute,
            entityName: 'test_entity',
            flags: [$flagMetadata],
            propertyType: 'App\\Enum\\Status',
        );

        $definition = $metadata->toDefinition();

        static::assertSame(FieldMetadata::class, $definition->getClass());

        $args = $definition->getArguments();
        static::assertCount(6, $args);
        static::assertSame(StringField::class, $args[0]);
        static::assertSame('name', $args[1]);
        static::assertInstanceOf(Definition::class, $args[2]);
        static::assertSame('test_entity', $args[3]);
        static::assertIsArray($args[4]);
        static::assertCount(1, $args[4]);
        static::assertInstanceOf(Definition::class, $args[4][0]);
        static::assertSame(FlagMetadata::class, $args[4][0]->getClass());
        static::assertSame('App\\Enum\\Status', $args[5]);
    }

    public function testToDefinitionWithEmptyFlags(): void
    {
        $attribute = new Field(FieldType::STRING);

        $metadata = new FieldMetadata(
            fieldClass: StringField::class,
            propertyName: 'name',
            attribute: $attribute,
            entityName: 'test_entity',
        );

        $definition = $metadata->toDefinition();
        $args = $definition->getArguments();

        static::assertIsArray($args[4]);
        static::assertCount(0, $args[4]);
    }
}
