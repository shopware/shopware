<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Util\UtilException;

/**
 * @internal
 */
#[CoversClass(Json::class)]
class JsonTest extends TestCase
{
    public function testDecodeArrayReturnsEmptyArrayOnEmptyString(): void
    {
        static::assertSame([], Json::decodeToList(''));
    }

    public function testDecodeArrayThrowsExceptionOnInvalidJsonString(): void
    {
        try {
            Json::decodeToList('["abc", "foo"');
            static::fail(UtilException::class . ' not thrown');
        } catch (UtilException $e) {
            static::assertEquals(UtilException::INVALID_JSON, $e->getErrorCode());
            static::assertEquals('JSON is invalid', $e->getMessage());
            static::assertInstanceOf(\JsonException::class, $e->getPrevious());
        }
    }

    public function testDecodeListThrowsExceptionOnDecodedObject(): void
    {
        try {
            Json::decodeToList('{"abc": "foo"}');
            static::fail(UtilException::class . ' not thrown');
        } catch (UtilException $e) {
            static::assertEquals(UtilException::INVALID_JSON_NOT_LIST, $e->getErrorCode());
            static::assertEquals('JSON cannot be decoded to a list', $e->getMessage());
        }
    }

    public function testDecodeListThrowsExceptionOnDecodedObjectWithNumericNonSequentialIndices(): void
    {
        try {
            Json::decodeToList('{"0": "abc", "2": "foo"}');
            static::fail(UtilException::class . ' not thrown');
        } catch (UtilException $e) {
            static::assertEquals(UtilException::INVALID_JSON_NOT_LIST, $e->getErrorCode());
            static::assertEquals('JSON cannot be decoded to a list', $e->getMessage());
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
            static::fail(UtilException::class . ' not thrown');
        } catch (UtilException $e) {
            static::assertEquals(UtilException::INVALID_JSON_NOT_LIST, $e->getErrorCode());
            static::assertEquals('JSON cannot be decoded to a list', $e->getMessage());
        }
    }

    public function testDecodeLIstCorrectlyDecodesList(): void
    {
        static::assertSame(['abc', 'foo'], Json::decodeToList('["abc", "foo"]'));
    }

    public function testDecodeListWithObjectsAsArrayListWithAssociativeArrays(): void
    {
        static::assertSame(
            [['name' => 'abc'], ['name' => 'foo']],
            Json::decodeToList('[{"name": "abc"}, {"name": "foo"}]')
        );
    }
}
