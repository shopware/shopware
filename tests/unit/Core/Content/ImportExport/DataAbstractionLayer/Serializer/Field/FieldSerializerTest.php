<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\ArrayStruct;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer
 */
#[Package('services-settings')]
class FieldSerializerTest extends TestCase
{
    /**
     * @dataProvider serializeDataProvider
     *
     * @throws \JsonException
     */
    public function testSerialize(Field $field, mixed $inputValue, mixed $expected): void
    {
        $fieldSerializer = new FieldSerializer();
        $config = new Config([], [], []);

        static::assertEquals($expected, $this->first($fieldSerializer->serialize($config, $field, $inputValue)));
    }

    /**
     * @dataProvider deserializeDataProvider
     *
     * @throws \JsonException
     */
    public function testDeserialize(Field $field, mixed $inputValue, mixed $expected): void
    {
        $fieldSerializer = new FieldSerializer();
        $config = new Config([], [], []);

        static::assertEquals($expected, $fieldSerializer->deserialize($config, $field, $inputValue));
    }

    /**
     * @return iterable<string, array{field: Field, inputValue: mixed, expected: mixed}>
     */
    public static function serializeDataProvider(): iterable
    {
        yield 'int field #1' => [
            'field' => new IntField('foo', 'foo'),
            'inputValue' => '',
            'expected' => null,
        ];

        yield 'int field #2' => [
            'field' => new IntField('foo', 'foo'),
            'inputValue' => 0,
            'expected' => '0',
        ];

        yield 'int field #3' => [
            'field' => new IntField('foo', 'foo'),
            'inputValue' => 3123412344321,
            'expected' => '3123412344321',
        ];

        yield 'bool field' => [
            'field' => new BoolField('foo', 'foo'),
            'inputValue' => true,
            'expected' => '1',
        ];

        yield 'json field' => [
            'field' => new JsonField('foo', 'foo'),
            'inputValue' => ['foo' => 'baz'],
            'expected' => '{"foo":"baz"}',
        ];

        yield 'blob field #1: string' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => 'plain string',
            'expected' => 'plain string',
        ];

        yield 'blob field #2: float' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => 123.23,
            'expected' => '123.23',
        ];

        yield 'blob field #3: null' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => null,
            'expected' => null,
        ];

        yield 'blob field #4: bool' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => true,
            'expected' => '1',
        ];

        yield 'blob field #5: array' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => ['foo' => 'baz'],
            'expected' => '{"foo":"baz"}',
        ];

        yield 'blob field #6: struct' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => new ArrayStruct(['foo' => 'baz']),
            'expected' => '{"extensions":[],"apiAlias":null,"foo":"baz"}',
        ];

        yield 'blob field #7: rule struct' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => new AndRule(),
            'expected' => '{"_name":"andContainer","rules":[]}',
        ];

        yield 'blob field #8: Stringable' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => new DummyStringable(),
            'expected' => 'dummy',
        ];
    }

    /**
     * @return iterable<string, array{field: Field, inputValue: mixed, expected: mixed}>
     */
    public static function deserializeDataProvider(): iterable
    {
        yield 'int field #1' => [
            'field' => new IntField('foo', 'foo'),
            'inputValue' => null,
            'expected' => '',
        ];

        yield 'int field #2' => [
            'field' => new IntField('foo', 'foo'),
            'inputValue' => '0',
            'expected' => 0,
        ];

        yield 'int field #3' => [
            'field' => new IntField('foo', 'foo'),
            'inputValue' => '3123412344321',
            'expected' => 3123412344321,
        ];

        yield 'bool field' => [
            'field' => new BoolField('foo', 'foo'),
            'inputValue' => '1',
            'expected' => true,
        ];

        yield 'json field' => [
            'field' => new JsonField('foo', 'foo'),
            'inputValue' => '{"foo":"baz"}',
            'expected' => ['foo' => 'baz'],
        ];

        yield 'blob field #1: string' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => 'plain string',
            'expected' => 'plain string',
        ];

        yield 'blob field #2: float' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => '123.23',
            'expected' => 123.23,
        ];

        yield 'blob field #3: null' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => null,
            'expected' => null,
        ];

        yield 'blob field #4: bool' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => '1',
            'expected' => true,
        ];

        yield 'blob field #5: array' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => '{"foo":"baz"}',
            'expected' => '{"foo":"baz"}',
        ];

        yield 'blob field #6: struct' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => '{"extensions":[],"apiAlias":null,"foo":"baz"}',
            'expected' => '{"extensions":[],"apiAlias":null,"foo":"baz"}',
        ];

        yield 'blob field #7: rule struct' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => '{"_name":"andContainer","rules":[]}',
            'expected' => '{"_name":"andContainer","rules":[]}',
        ];

        yield 'blob field #8: Stringable' => [
            'field' => new BlobField('foo', 'foo'),
            'inputValue' => 'dummy',
            'expected' => new DummyStringable(),
        ];
    }

    /**
     * @param iterable<mixed>|null $iterable
     */
    private function first(?iterable $iterable): mixed
    {
        if ($iterable === null) {
            return null;
        }

        foreach ($iterable as $value) {
            return $value;
        }

        return null;
    }
}

/**
 * @internal
 */
class DummyStringable implements \Stringable
{
    public function __toString(): string
    {
        return 'dummy';
    }
}
