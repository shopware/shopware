<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\Json;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Util\Json
 */
class JsonTest extends TestCase
{
    public function testDecodeArrayReturnsEmptyArrayOnEmptyString(): void
    {
        static::assertSame([], Json::decodeArray(''));
    }

    public function testDecodeArrayThrowsJsonExceptionOnInvalidJsonString(): void
    {
        static::expectException(\JsonException::class);
        Json::decodeArray('["abc", "foo"');
    }

    public function testDecodeArrayThrowsUnexcpectedValueExceptionOnDecodedObject(): void
    {
        static::expectException(\UnexpectedValueException::class);
        Json::decodeArray('{"abc": "foo"}');
    }

    public function testDecodeArrayThrowsUnexcpectedValueExceptionOnDecodedObjectWithNumericNonSequentialIndices(): void
    {
        static::expectException(\UnexpectedValueException::class);
        Json::decodeArray('{"0": "abc", "2": "foo"}');
    }

    public function testDecodeArrayDecodesObjectWithSequentialNumericIndicesAsArray(): void
    {
        static::assertSame(['abc', 'foo'], Json::decodeArray('{"0": "abc", "1": "foo"}'));
    }

    public function testDecodeArrayThrowsUnexcpectedValueExceptionOnDecodedString(): void
    {
        static::expectException(\UnexpectedValueException::class);
        Json::decodeArray('"abc"');
    }

    public function testDecodeArrayThrowsUnexcpectedValueExceptionOnDecodedInt(): void
    {
        static::expectException(\UnexpectedValueException::class);
        Json::decodeArray('123');
    }

    public function testDecodeArrayThrowsUnexcpectedValueExceptionOnDecodedFloat(): void
    {
        static::expectException(\UnexpectedValueException::class);
        Json::decodeArray('12.01');
    }

    public function testDecodeArrayThrowsUnexcpectedValueExceptionOnDecodedBoolean(): void
    {
        static::expectException(\UnexpectedValueException::class);
        Json::decodeArray('false');
    }

    public function testDecodeArrayThrowsUnexcpectedValueExceptionOnDecodedNull(): void
    {
        static::expectException(\UnexpectedValueException::class);
        Json::decodeArray('null');
    }

    public function testDecodeArrayCorrectlyDecodesArray(): void
    {
        static::assertSame(['abc', 'foo'], Json::decodeArray('["abc", "foo"]'));
    }

    public function testDecodeArrayWithObjectsAsArrayListWithAssociativeArrays(): void
    {
        static::assertSame(
            [['name' => 'abc'], ['name' => 'foo']],
            Json::decodeArray('[{"name": "abc"}, {"name": "foo"}]')
        );
    }
}
