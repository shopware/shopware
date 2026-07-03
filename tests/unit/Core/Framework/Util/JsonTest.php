<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\Exception\JsonDecodingException;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Util\UtilException;

/**
 * @internal
 */
#[CoversClass(Json::class)]
#[CoversClass(JsonDecodingException::class)]
class JsonTest extends TestCase
{
    public function testDecodeListReturnsEmptyArrayOnEmptyString(): void
    {
        static::assertSame([], Json::decodeToList(''));
    }

    public function testDecodeListThrowsExceptionOnEmptyStringWhenEmptyStringIsNotAllowed(): void
    {
        try {
            Json::decodeToList('', false);
            static::fail(JsonDecodingException::class . ' not thrown');
        } catch (JsonDecodingException $e) {
            static::assertSame(UtilException::INVALID_JSON, $e->getErrorCode());
            static::assertSame('JSON is invalid', $e->getMessage());
            static::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    public function testDecodeListThrowsExceptionOnInvalidJsonString(): void
    {
        try {
            Json::decodeToList('["abc", "foo"');
            static::fail(JsonDecodingException::class . ' not thrown');
        } catch (JsonDecodingException $e) {
            static::assertSame(UtilException::INVALID_JSON, $e->getErrorCode());
            static::assertSame('JSON is invalid', $e->getMessage());
            static::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    public function testDecodeListThrowsExceptionOnDecodedObject(): void
    {
        try {
            Json::decodeToList('{"abc": "foo"}');
            static::fail(JsonDecodingException::class . ' not thrown');
        } catch (JsonDecodingException $e) {
            static::assertSame(UtilException::INVALID_JSON_NOT_LIST, $e->getErrorCode());
            static::assertSame('JSON cannot be decoded to a list', $e->getMessage());
        }
    }

    public function testDecodeListThrowsExceptionOnDecodedObjectWithNumericNonSequentialIndices(): void
    {
        try {
            Json::decodeToList('{"0": "abc", "2": "foo"}');
            static::fail(JsonDecodingException::class . ' not thrown');
        } catch (JsonDecodingException $e) {
            static::assertSame(UtilException::INVALID_JSON_NOT_LIST, $e->getErrorCode());
            static::assertSame('JSON cannot be decoded to a list', $e->getMessage());
        }
    }

    public function testDecodeListDecodesObjectWithSequentialNumericIndices(): void
    {
        static::assertSame(['abc', 'foo'], Json::decodeToList('{"0": "abc", "1": "foo"}'));
    }

    /**
     * @return array<string, array<string>>
     */
    public static function nonArrayInput(): array
    {
        return [
            'string' => ['"abc"'],
            'int' => ['123'],
            'float' => ['12.01'],
            'false' => ['false'],
            'null' => ['null'],
        ];
    }

    #[DataProvider('nonArrayInput')]
    public function testDecodeListThrowsExceptionOnNonArrayInputs(mixed $input): void
    {
        try {
            Json::decodeToList($input);
            static::fail(JsonDecodingException::class . ' not thrown');
        } catch (JsonDecodingException $e) {
            static::assertSame(UtilException::INVALID_JSON_NOT_LIST, $e->getErrorCode());
            static::assertSame('JSON cannot be decoded to a list', $e->getMessage());
        }
    }

    public function testDecodeListCorrectlyDecodesList(): void
    {
        static::assertSame(['abc', 'foo'], Json::decodeToList('["abc", "foo"]'));
    }

    public function testDecodeArrayThrowsExceptionOnEmptyString(): void
    {
        try {
            Json::decodeToArray('');
            static::fail(JsonDecodingException::class . ' not thrown');
        } catch (JsonDecodingException $e) {
            static::assertSame(UtilException::INVALID_JSON, $e->getErrorCode());
            static::assertSame('JSON is invalid', $e->getMessage());
            static::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    public function testDecodeArrayThrowsExceptionOnInvalidJsonString(): void
    {
        try {
            Json::decodeToArray('{"abc": "foo"');
            static::fail(JsonDecodingException::class . ' not thrown');
        } catch (JsonDecodingException $e) {
            static::assertSame(UtilException::INVALID_JSON, $e->getErrorCode());
            static::assertSame('JSON is invalid', $e->getMessage());
            static::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    #[DataProvider('nonArrayInput')]
    public function testDecodeArrayThrowsExceptionOnNonArrayInputs(mixed $input): void
    {
        try {
            Json::decodeToArray($input);
            static::fail(JsonDecodingException::class . ' not thrown');
        } catch (JsonDecodingException $e) {
            static::assertSame(UtilException::INVALID_JSON_NOT_ARRAY, $e->getErrorCode());
            static::assertSame('JSON cannot be decoded to an array', $e->getMessage());
        }
    }

    public function testDecodeArrayCorrectlyDecodesList(): void
    {
        static::assertSame(['abc', 'foo'], Json::decodeToArray('["abc", "foo"]'));
    }

    public function testDecodeArrayCorrectlyDecodesObject(): void
    {
        static::assertSame(['abc' => 'foo'], Json::decodeToArray('{"abc": "foo"}'));
    }

    public function testDecodeListWithObjectsAsArrayListWithAssociativeArrays(): void
    {
        static::assertSame(
            [['name' => 'abc'], ['name' => 'foo']],
            Json::decodeToList('[{"name": "abc"}, {"name": "foo"}]')
        );
    }

    public function testEncodeIgnoresInvalidUtf8Characters(): void
    {
        static::assertSame('"something another"', Json::encode("something\x82 another"));
    }
}
