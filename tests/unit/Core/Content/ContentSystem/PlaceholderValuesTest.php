<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PlaceholderValues::class)]
class PlaceholderValuesTest extends TestCase
{
    /**
     * @param array<string, string|int|bool|float> $values
     */
    #[DataProvider('validScalarValuesProvider')]
    #[TestDox('creates instance with valid scalar values')]
    public function testFrom(array $values): void
    {
        $placeholderValues = PlaceholderValues::from($values);

        static::assertSame($values, $placeholderValues->all());
    }

    /**
     * @return \Generator<string, array{array<string, string|int|bool|float>}>
     */
    public static function validScalarValuesProvider(): \Generator
    {
        yield 'string value' => [['key' => 'value']];
        yield 'int value' => [['count' => 42]];
        yield 'bool value' => [['active' => true]];
        yield 'float value' => [['price' => 9.99]];
        yield 'mixed scalar types' => [['name' => 'product', 'count' => 5, 'active' => false, 'price' => 1.5]];
    }

    #[TestDox('returns all stored values')]
    public function testAllReturnsAllValues(): void
    {
        $values = ['foo' => 'bar', 'baz' => 123];
        $placeholderValues = PlaceholderValues::from($values);

        static::assertSame($values, $placeholderValues->all());
    }

    #[TestDox('throws exception for non-scalar array value')]
    public function testFromThrowsForNonScalarValue(): void
    {
        static::expectExceptionObject(
            ContentSystemException::invalidMapValue('PlaceholderValues', 'key', 'scalar', 'array')
        );

        /** @phpstan-ignore-next-line argument.type */
        PlaceholderValues::from(['key' => ['nested' => 'array']]);
    }

    #[TestDox('throws exception for null value')]
    public function testFromThrowsForNullValue(): void
    {
        static::expectExceptionObject(
            ContentSystemException::invalidMapValue('PlaceholderValues', 'key', 'scalar', 'null')
        );

        /** @phpstan-ignore-next-line argument.type */
        PlaceholderValues::from(['key' => null]);
    }

    #[TestDox('accepts empty array and returns empty values')]
    public function testFromAcceptsEmptyArray(): void
    {
        $placeholderValues = PlaceholderValues::from([]);

        static::assertSame([], $placeholderValues->all());
    }
}
