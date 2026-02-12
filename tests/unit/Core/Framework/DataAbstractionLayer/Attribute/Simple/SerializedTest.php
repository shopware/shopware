<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute\Simple;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Serialized;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SerializedField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Serialized::class)]
final class SerializedTest extends TestCase
{
    public function testDefaultSerializer(): void
    {
        $attribute = new Serialized();

        $field = $attribute->createField(
            'data',
            'data',
            'custom_entity'
        );

        static::assertInstanceOf(SerializedField::class, $field);
        static::assertSame('data', $field->getPropertyName());
        static::assertSame('data', $field->getStorageName());
    }

    public function testCustomSerializer(): void
    {
        $attribute = new Serialized(serializer: JsonFieldSerializer::class);

        $field = $attribute->createField(
            'data',
            'data',
            'custom_entity'
        );

        static::assertInstanceOf(SerializedField::class, $field);
        static::assertSame(JsonFieldSerializer::class, $attribute->serializer);
    }

    public function testTranslatedField(): void
    {
        $attribute = new Serialized(translated: true);

        $field = $attribute->createField(
            'data',
            'data',
            'custom_entity'
        );

        static::assertInstanceOf(SerializedField::class, $field);
        static::assertTrue($attribute->translated);
    }

    public function testGetFieldClass(): void
    {
        $attribute = new Serialized();

        static::assertSame(SerializedField::class, $attribute->getFieldClass());
    }

    public function testFromArray(): void
    {
        $data = [
            'serializer' => JsonFieldSerializer::class,
            'api' => true,
            'translated' => false,
            'column' => 'custom_data',
            'nullable' => false,
            'type' => Serialized::TYPE,
        ];

        $attribute = Serialized::fromArray($data);

        static::assertSame(JsonFieldSerializer::class, $attribute->serializer);
        static::assertTrue($attribute->api);
        static::assertFalse($attribute->translated);
        static::assertSame('custom_data', $attribute->column);
        static::assertFalse($attribute->nullable);
    }

    public function testDefaultValues(): void
    {
        $attribute = new Serialized();

        static::assertSame(StringFieldSerializer::class, $attribute->serializer);
        static::assertFalse($attribute->api);
        static::assertFalse($attribute->translated);
    }

    public function testToDefinition(): void
    {
        $attribute = new Serialized(
            serializer: JsonFieldSerializer::class,
            api: true,
            translated: true,
            column: 'custom_col'
        );
        $attribute->nullable = false;

        $definition = $attribute->toDefinition();

        static::assertSame([Serialized::class, 'fromArray'], $definition->getFactory());

        $args = $definition->getArguments();
        static::assertCount(1, $args);
        static::assertIsArray($args[0]);
        static::assertSame(JsonFieldSerializer::class, $args[0]['serializer']);
        static::assertTrue($args[0]['api']);
        static::assertTrue($args[0]['translated']);
        static::assertSame('custom_col', $args[0]['column']);
        static::assertFalse($args[0]['nullable']);
    }
}
