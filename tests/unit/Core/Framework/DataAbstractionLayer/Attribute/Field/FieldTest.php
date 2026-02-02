<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EnumField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field as DalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\AttributeTestFixtures;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Field::class)]
final class FieldTest extends TestCase
{
    /**
     * @param class-string<DalField> $expectedFieldClass
     */
    #[DataProvider('scalarTypeFieldCreationProvider')]
    public function testScalarTypeWithDefaultColumn(
        string $type,
        string $expectedFieldClass
    ): void {
        $attribute = new Field($type);
        $field = $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME,
            AttributeTestFixtures::COLUMN_NAME,
            AttributeTestFixtures::ENTITY_NAME
        );

        static::assertInstanceOf($expectedFieldClass, $field);
        static::assertSame(AttributeTestFixtures::PROPERTY_NAME, $field->getPropertyName());
        static::assertInstanceOf(StorageAware::class, $field);
        static::assertSame(AttributeTestFixtures::COLUMN_NAME, $field->getStorageName());
    }

    /**
     * @param class-string<DalField> $expectedFieldClass
     */
    #[DataProvider('scalarTypeFieldCreationProvider')]
    public function testScalarTypeWithCustomColumn(
        string $type,
        string $expectedFieldClass
    ): void {
        $customColumn = 'custom_col';
        $attribute = new Field($type, column: $customColumn);
        $field = $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME,
            AttributeTestFixtures::COLUMN_NAME,
            AttributeTestFixtures::ENTITY_NAME
        );

        static::assertInstanceOf($expectedFieldClass, $field);
        static::assertSame(AttributeTestFixtures::PROPERTY_NAME, $field->getPropertyName());
        static::assertInstanceOf(StorageAware::class, $field);
        static::assertSame($customColumn, $field->getStorageName());
    }

    public function testEnumFieldWithStringEnum(): void
    {
        $attribute = new Field(FieldType::ENUM);
        $field = $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME,
            AttributeTestFixtures::COLUMN_NAME,
            AttributeTestFixtures::ENTITY_NAME,
            AttributeTestFixtures::STRING_ENUM_CLASS
        );

        static::assertInstanceOf(EnumField::class, $field);
        static::assertSame(AttributeTestFixtures::PROPERTY_NAME, $field->getPropertyName());
        static::assertSame(AttributeTestFixtures::COLUMN_NAME, $field->getStorageName());
    }

    public function testEnumFieldWithIntEnum(): void
    {
        $attribute = new Field(FieldType::ENUM);
        $field = $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME,
            AttributeTestFixtures::COLUMN_NAME,
            AttributeTestFixtures::ENTITY_NAME,
            AttributeTestFixtures::INT_ENUM_CLASS
        );

        static::assertInstanceOf(EnumField::class, $field);
        static::assertSame(AttributeTestFixtures::PROPERTY_NAME, $field->getPropertyName());
        static::assertSame(AttributeTestFixtures::COLUMN_NAME, $field->getStorageName());
    }

    public function testNonBackedEnumThrowsException(): void
    {
        $this->expectException(DataAbstractionLayerException::class);

        $attribute = new Field(FieldType::ENUM);
        $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME,
            AttributeTestFixtures::COLUMN_NAME,
            AttributeTestFixtures::ENTITY_NAME,
            TestNonBackedEnum::class
        );
    }

    public function testNullPropertyTypeThrowsException(): void
    {
        $this->expectException(DataAbstractionLayerException::class);

        $attribute = new Field(FieldType::ENUM);
        $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME,
            AttributeTestFixtures::COLUMN_NAME,
            AttributeTestFixtures::ENTITY_NAME,
            null
        );
    }

    public function testDirectFieldClass(): void
    {
        $attribute = new Field(PriceField::class);
        $field = $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME,
            AttributeTestFixtures::COLUMN_NAME,
            AttributeTestFixtures::ENTITY_NAME
        );

        static::assertInstanceOf(PriceField::class, $field);
        static::assertSame(AttributeTestFixtures::PROPERTY_NAME, $field->getPropertyName());
        static::assertSame(AttributeTestFixtures::COLUMN_NAME, $field->getStorageName());
    }

    public function testUnknownFieldTypeThrowsException(): void
    {
        $this->expectException(DataAbstractionLayerException::class);

        $attribute = new Field('unknown_type');
        $attribute->createField(
            AttributeTestFixtures::PROPERTY_NAME,
            AttributeTestFixtures::COLUMN_NAME,
            AttributeTestFixtures::ENTITY_NAME
        );
    }

    public function testGetFieldClassForScalarType(): void
    {
        $attribute = new Field(FieldType::STRING);

        static::assertSame(StringField::class, $attribute->getFieldClass());
    }

    public function testGetFieldClassForEnumType(): void
    {
        $attribute = new Field(FieldType::ENUM);

        static::assertSame(EnumField::class, $attribute->getFieldClass());
    }

    public function testGetFieldClassForDirectFieldClass(): void
    {
        $attribute = new Field(PriceField::class);

        static::assertSame(PriceField::class, $attribute->getFieldClass());
    }

    public function testFromArray(): void
    {
        $data = [
            'type' => FieldType::INT,
            'column' => 'test_col',
            'translated' => false,
            'api' => false,
            'nullable' => true,
        ];

        $attribute = Field::fromArray($data);

        static::assertSame(FieldType::INT, $attribute->type);
        static::assertSame('test_col', $attribute->column);
        static::assertFalse($attribute->translated);
        static::assertFalse($attribute->api);
        static::assertTrue($attribute->nullable);
    }

    public function testToDefinition(): void
    {
        $attribute = new Field(
            FieldType::STRING,
            translated: true,
            api: true,
            column: 'custom_col'
        );
        $attribute->nullable = false;

        $definition = $attribute->toDefinition();

        static::assertSame([Field::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame(FieldType::STRING, $args[0]['type']);
        static::assertTrue($args[0]['translated']);
        static::assertTrue($args[0]['api']);
        static::assertSame('custom_col', $args[0]['column']);
        static::assertFalse($args[0]['nullable']);
    }

    /**
     * @return \Generator<string, array{type: string, expectedFieldClass: class-string<DalField>}>
     */
    public static function scalarTypeFieldCreationProvider(): \Generator
    {
        $types = [
            FieldType::INT => IntField::class,
            FieldType::FLOAT => FloatField::class,
            FieldType::STRING => StringField::class,
            FieldType::TEXT => LongTextField::class,
            FieldType::BOOL => BoolField::class,
            FieldType::DATETIME => DateTimeField::class,
            FieldType::DATE => DateField::class,
            FieldType::JSON => JsonField::class,
            FieldType::UUID => IdField::class,
            FieldType::DATE_INTERVAL => DateIntervalField::class,
            FieldType::TIME_ZONE => TimeZoneField::class,
        ];

        foreach ($types as $type => $fieldClass) {
            $lastBackslashPos = \strrpos($fieldClass, '\\');
            $shortName = $lastBackslashPos !== false ? \substr($fieldClass, $lastBackslashPos + 1) : $fieldClass;
            yield "{$shortName}" => [
                'type' => $type,
                'expectedFieldClass' => $fieldClass,
            ];
        }
    }
}

/**
 * Test non-backed enum for exception testing.
 *
 * @internal
 */
enum TestNonBackedEnum
{
    case CASE_ONE;
    case CASE_TWO;
}
