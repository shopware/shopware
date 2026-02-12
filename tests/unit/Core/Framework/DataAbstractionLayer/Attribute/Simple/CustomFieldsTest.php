<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\Simple;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields as CustomFieldsField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CustomFields::class)]
final class CustomFieldsTest extends TestCase
{
    public function testCreateField(): void
    {
        $attribute = new CustomFields();

        $field = $attribute->createField(
            'customFields',
            'custom_fields',
            'product'
        );

        static::assertInstanceOf(CustomFieldsField::class, $field);
        static::assertSame('customFields', $field->getPropertyName());
        static::assertSame('custom_fields', $field->getStorageName());
    }

    public function testTranslatedField(): void
    {
        $attribute = new CustomFields(translated: true);

        $field = $attribute->createField(
            'customFields',
            'custom_fields',
            'product'
        );

        static::assertInstanceOf(CustomFieldsField::class, $field);
        static::assertTrue($attribute->translated);
    }

    public function testCustomColumn(): void
    {
        $attribute = new CustomFields(column: 'custom_col');

        $field = $attribute->createField(
            'customFields',
            'custom_fields',
            'product'
        );

        static::assertInstanceOf(CustomFieldsField::class, $field);
        static::assertSame('custom_col', $field->getStorageName());
    }

    public function testGetFieldClass(): void
    {
        $attribute = new CustomFields();

        static::assertSame(CustomFieldsField::class, $attribute->getFieldClass());
    }

    public function testFromArray(): void
    {
        $data = [
            'translated' => true,
            'column' => 'custom_data',
            'nullable' => false,
            'type' => CustomFields::TYPE,
            'api' => true,
        ];

        $attribute = CustomFields::fromArray($data);

        static::assertTrue($attribute->translated);
        static::assertSame('custom_data', $attribute->column);
        static::assertFalse($attribute->nullable);
        static::assertTrue($attribute->api);
    }

    public function testToDefinition(): void
    {
        $attribute = new CustomFields(translated: true, column: 'custom_col');
        $attribute->nullable = false;

        $definition = $attribute->toDefinition();

        static::assertSame([CustomFields::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame(CustomFields::TYPE, $args[0]['type']);
        static::assertTrue($args[0]['translated']);
        static::assertTrue($args[0]['api']); // CustomFields always has api: true
        static::assertSame('custom_col', $args[0]['column']);
        static::assertFalse($args[0]['nullable']);
    }
}
