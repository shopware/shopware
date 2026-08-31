<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Framework\Util\UtilException;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Json::class)]
class JsonTest extends TestCase
{
    public function testDecodeListReturnsEmptyArrayOnEmptyString(): void
    {
        static::assertSame([], Json::decodeToList(''));
    }

    public function testDecodeListThrowsExceptionOnEmptyStringWhenEmptyStringIsNotAllowed(): void
    {
        $this->expectExceptionObject(UtilException::invalidJson(new \JsonException('Syntax error')));

        Json::decodeToList('', false);
    }

    public function testDecodeListThrowsExceptionOnInvalidJsonString(): void
    {
        $this->expectExceptionObject(UtilException::invalidJson(new \JsonException('Syntax error')));

        Json::decodeToList('["abc", "foo"');
    }

    public function testDecodeListThrowsExceptionOnDecodedObject(): void
    {
        $this->expectExceptionObject(UtilException::invalidJsonNotList());

        Json::decodeToList('{"abc": "foo"}');
    }

    public function testDecodeListThrowsExceptionOnDecodedObjectWithNumericNonSequentialIndices(): void
    {
        $this->expectExceptionObject(UtilException::invalidJsonNotList());

        Json::decodeToList('{"0": "abc", "2": "foo"}');
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
        $this->expectExceptionObject(UtilException::invalidJsonNotList());

        Json::decodeToList($input);
    }

    public function testDecodeListCorrectlyDecodesList(): void
    {
        static::assertSame(['abc', 'foo'], Json::decodeToList('["abc", "foo"]'));
    }

    public function testDecodeArrayThrowsExceptionOnEmptyString(): void
    {
        $this->expectExceptionObject(UtilException::invalidJson(new \JsonException('Syntax error')));

        Json::decodeToArray('');
    }

    public function testDecodeArrayThrowsExceptionOnInvalidJsonString(): void
    {
        $this->expectExceptionObject(UtilException::invalidJson(new \JsonException('Syntax error')));

        Json::decodeToArray('{"abc": "foo"');
    }

    #[DataProvider('nonArrayInput')]
    public function testDecodeArrayThrowsExceptionOnNonArrayInputs(mixed $input): void
    {
        $this->expectExceptionObject(UtilException::invalidJsonNotArray());

        Json::decodeToArray($input);
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
