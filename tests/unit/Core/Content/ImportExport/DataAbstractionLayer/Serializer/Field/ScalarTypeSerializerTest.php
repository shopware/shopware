<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\ScalarTypeSerializer;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Processing\Mapping\Mapping;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(ScalarTypeSerializer::class)]
class ScalarTypeSerializerTest extends TestCase
{
    public function testDeserializeInt(): void
    {
        $config = new Config([], [], []);
        $field = new IntField('foo', 'bar');
        $value = '1268';

        static::assertSame(1268, ScalarTypeSerializer::deserializeInt($config, $field, $value));
    }

    public function testDeserializeIntWithBrokenValueAndEmptyMappingReturnsNull(): void
    {
        $config = new Config([], [], []);
        $field = new IntField('foo', 'bar');
        $value = 'as1268ds';

        static::assertNull(ScalarTypeSerializer::deserializeInt($config, $field, $value));
    }

    public function testDeserializeIntWithEmptyValueAndNotRequiredReturnsNull(): void
    {
        $config = new Config([new Mapping('bar')], [], []);
        $field = new IntField('foo', 'bar');
        $value = '';

        static::assertNull(ScalarTypeSerializer::deserializeInt($config, $field, $value));
    }

    public function testDeserializeIntWithBrokenValueAndNotRequiredThrowsException(): void
    {
        $config = new Config([new Mapping('bar')], [], []);
        $field = new IntField('foo', 'bar');
        $value = 'as1268ds';

        $this->expectException(ImportExportException::class);
        $this->expectExceptionMessage('Deserialization failed for field "bar" with value "as1268ds" to type "integer"');

        ScalarTypeSerializer::deserializeInt($config, $field, $value);
    }

    public function testDeserializeIntWithBrokenValueAndIsRequiredThrowsException(): void
    {
        $config = new Config([new Mapping('bar', 'bar', 0, null, null, true)], [], []);
        $field = new IntField('foo', 'bar');
        $value = 'as1268ds';

        $this->expectException(ImportExportException::class);
        $this->expectExceptionMessage('Deserialization failed for field "bar" with value "as1268ds" to type "integer"');

        ScalarTypeSerializer::deserializeInt($config, $field, $value);
    }

    #[DataProvider('boolValues')]
    public function testDeserializeBool(string $value, bool $expected): void
    {
        $config = new Config([], [], []);
        $field = new BoolField('foo', 'bar');

        static::assertSame($expected, ScalarTypeSerializer::deserializeBool($config, $field, $value));
    }

    /**
     * @return iterable<array-key, array{value: string, expected: bool}>
     */
    public static function boolValues(): iterable
    {
        yield 'string on' => ['value' => 'on', 'expected' => true];
        yield 'string On' => ['value' => 'On', 'expected' => true];
        yield 'string ON' => ['value' => 'ON', 'expected' => true];
        yield 'string off' => ['value' => 'off', 'expected' => false];
        yield 'string Off' => ['value' => 'Off', 'expected' => false];
        yield 'string OFF' => ['value' => 'OFF', 'expected' => false];
        yield 'string yes' => ['value' => 'yes', 'expected' => true];
        yield 'string Yes' => ['value' => 'Yes', 'expected' => true];
        yield 'string YES' => ['value' => 'YES', 'expected' => true];
        yield 'string no' => ['value' => 'no', 'expected' => false];
        yield 'string No' => ['value' => 'No', 'expected' => false];
        yield 'string NO' => ['value' => 'NO', 'expected' => false];
        yield 'string 0' => ['value' => '0', 'expected' => false];
        yield 'string 1' => ['value' => '1', 'expected' => true];
        yield 'string true' => ['value' => 'true', 'expected' => true];
        yield 'string True' => ['value' => 'True', 'expected' => true];
        yield 'string TRUE' => ['value' => 'TRUE', 'expected' => true];
        yield 'string false' => ['value' => 'false', 'expected' => false];
        yield 'string False' => ['value' => 'False', 'expected' => false];
        yield 'string FALSE' => ['value' => 'FALSE', 'expected' => false];
    }

    public function testDeserializeBoolWithBrokenValueAndEmptyMappingReturnsNull(): void
    {
        $config = new Config([], [], []);
        $field = new BoolField('foo', 'bar');
        $value = 'NotABoolValue';

        static::assertNull(ScalarTypeSerializer::deserializeBool($config, $field, $value));
    }

    public function testDeserializeBoolWithBrokenValueAndNotRequiredThrowsException(): void
    {
        $config = new Config([new Mapping('bar')], [], []);
        $field = new BoolField('foo', 'bar');
        $value = 'NotABoolValue';

        $this->expectException(ImportExportException::class);
        $this->expectExceptionMessage('Deserialization failed for field "bar" with value "NotABoolValue" to type "boolean"');

        ScalarTypeSerializer::deserializeBool($config, $field, $value);
    }

    public function testDeserializeBoolWithBrokenValueAndIsRequiredThrowsException(): void
    {
        $config = new Config([new Mapping('bar', 'bar', 0, null, null, true)], [], []);
        $field = new BoolField('foo', 'bar');
        $value = 'NotABoolValue';

        $this->expectException(ImportExportException::class);
        $this->expectExceptionMessage('Deserialization failed for field "bar" with value "NotABoolValue" to type "boolean"');

        ScalarTypeSerializer::deserializeBool($config, $field, $value);
    }
}
