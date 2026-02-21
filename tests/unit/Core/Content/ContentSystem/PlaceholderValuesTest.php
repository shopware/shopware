<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;

/**
 * @internal
 */
#[CoversClass(PlaceholderValues::class)]
class PlaceholderValuesTest extends TestCase
{
    /**
     * @param array<string, string|int|bool|float> $values
     */
    #[DataProvider('validScalarValuesProvider')]
    #[TestDox('creates instance with valid scalar values')]
    public function testFromCreatesInstancePreservingAllScalarValues(array $values): void
    {
        $placeholderValues = PlaceholderValues::from($values);

        static::assertSame($values, $placeholderValues->all());
    }

    /**
     * @return \Generator<string, array{array<string, string|int|bool|float>}>
     */
    public static function validScalarValuesProvider(): \Generator
    {
        yield 'mixed scalar types' => [['name' => 'product', 'count' => 5, 'active' => false, 'price' => 1.5]];
    }

    #[TestDox('accepts empty array and returns empty values')]
    public function testFromAcceptsEmptyArray(): void
    {
        $placeholderValues = PlaceholderValues::from([]);

        static::assertSame([], $placeholderValues->all());
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('nonScalarValueProvider')]
    #[TestDox('throws exception when value is not scalar')]
    public function testFromThrowsForNonScalarValue(array $values, string $expectedType): void
    {
        static::expectExceptionObject(
            ContentSystemException::invalidMapValue('PlaceholderValues', 'key', 'scalar', $expectedType)
        );

        PlaceholderValues::from($values);
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, string}>
     */
    public static function nonScalarValueProvider(): \Generator
    {
        yield 'array value' => [['key' => ['nested' => 'array']], 'array'];
        yield 'null value' => [['key' => null], 'null'];
    }
}
