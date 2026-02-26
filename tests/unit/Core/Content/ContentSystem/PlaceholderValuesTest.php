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
    #[DataProvider('createsInstanceWithScalarValuesProvider')]
    #[TestDox('creates instance with valid scalar values')]
    public function testFromCreatesInstancePreservingAllScalarValues(array $values): void
    {
        $placeholderValues = PlaceholderValues::from($values);

        static::assertSame($values, $placeholderValues->all());
    }

    #[TestDox('accepts empty array and returns empty values')]
    public function testFromAcceptsEmptyArray(): void
    {
        $placeholderValues = PlaceholderValues::from([]);

        static::assertSame([], $placeholderValues->all());
    }

    #[TestDox('throws exception when key is not a string')]
    public function testFromThrowsForNonStringKey(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidMapKey('PlaceholderValues', 'int')
        );

        PlaceholderValues::from([0 => 'value']); // @phpstan-ignore argument.type
    }

    /**
     * @param array<string, mixed> $values
     */
    #[DataProvider('throwsForNonScalarValueProvider')]
    #[TestDox('throws exception when value is not scalar')]
    public function testFromThrowsForNonScalarValue(array $values, string $expectedType): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidMapValue('PlaceholderValues', 'key', 'scalar', $expectedType)
        );

        PlaceholderValues::from($values);
    }

    /**
     * @return \Generator<string, array{array<string, string|int|bool|float>}>
     */
    public static function createsInstanceWithScalarValuesProvider(): \Generator
    {
        yield 'all four scalar types accepted (string, int, bool, float)' => [['name' => 'product', 'count' => 5, 'active' => false, 'price' => 1.5]];
    }

    /**
     * @return \Generator<string, array{array<string, mixed>, string}>
     */
    public static function throwsForNonScalarValueProvider(): \Generator
    {
        yield 'null is not scalar' => [['key' => null], 'null'];
    }
}
