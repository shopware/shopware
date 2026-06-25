<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Util\Exception\ComparatorException;
use Shopware\Core\Framework\Util\FloatComparator;
use Shopware\Core\Framework\Util\UtilException;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(FloatComparator::class)]
class FloatComparatorTest extends TestCase
{
    #[DataProvider('compareDataProvider')]
    public function testCompare(string $operator, float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::compare($a, $b, $operator));
    }

    /**
     * @deprecated tag:v6.8.0 - reason: see UtilException::operatorNotSupported - to be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCompareThrowExceptionDeprecated(): void
    {
        $this->expectExceptionObject(ComparatorException::operatorNotSupported('empty'));

        FloatComparator::compare(1, 2, 'empty');
    }

    public function testCompareThrowException(): void
    {
        $this->expectExceptionObject(UtilException::operatorNotSupported('empty'));

        FloatComparator::compare(1, 2, 'empty');
    }

    /**
     * @return iterable<string, array<string, string|float|bool>>
     */
    public static function compareDataProvider(): iterable
    {
        yield 'not equal operator accepts different values' => [
            'operator' => Rule::OPERATOR_NEQ,
            'a' => 1,
            'b' => 2,
            'expected' => true,
        ];
        yield 'not equal operator rejects equal values' => [
            'operator' => Rule::OPERATOR_NEQ,
            'a' => 1,
            'b' => 1,
            'expected' => false,
        ];
        yield 'greater than or equal operator accepts equal values' => [
            'operator' => Rule::OPERATOR_GTE,
            'a' => 1,
            'b' => 1,
            'expected' => true,
        ];
        yield 'greater than or equal operator rejects lower values' => [
            'operator' => Rule::OPERATOR_GTE,
            'a' => 1,
            'b' => 2,
            'expected' => false,
        ];
        yield 'less than or equal operator accepts equal values' => [
            'operator' => Rule::OPERATOR_LTE,
            'a' => 1,
            'b' => 1,
            'expected' => true,
        ];
        yield 'less than or equal operator rejects higher values' => [
            'operator' => Rule::OPERATOR_LTE,
            'a' => 1,
            'b' => 0,
            'expected' => false,
        ];
        yield 'equal operator accepts equal values' => [
            'operator' => Rule::OPERATOR_EQ,
            'a' => 1,
            'b' => 1,
            'expected' => true,
        ];
        yield 'equal operator rejects different values' => [
            'operator' => Rule::OPERATOR_EQ,
            'a' => 1,
            'b' => 2,
            'expected' => false,
        ];
        yield 'greater than operator accepts higher values' => [
            'operator' => Rule::OPERATOR_GT,
            'a' => 2,
            'b' => 1,
            'expected' => true,
        ];
        yield 'greater than operator rejects lower values' => [
            'operator' => Rule::OPERATOR_GT,
            'a' => 1,
            'b' => 2,
            'expected' => false,
        ];
        yield 'less than operator accepts lower values' => [
            'operator' => Rule::OPERATOR_LT,
            'a' => 1,
            'b' => 2,
            'expected' => true,
        ];
        yield 'less than operator rejects higher values' => [
            'operator' => Rule::OPERATOR_LT,
            'a' => 2,
            'b' => 1,
            'expected' => false,
        ];
    }

    #[DataProvider('equalsDataProvider')]
    public function testEquals(float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::equals($a, $b));
    }

    public function testCast(): void
    {
        $x = 0.631 * 5;

        /** @phpstan-ignore identical.alwaysFalse, staticMethod.alreadyNarrowedType (check is always false, which is exactly the point) */
        static::assertFalse($x === 3.155);
        static::assertTrue(FloatComparator::cast($x) === 3.155);
    }

    /**
     * @return iterable<string, array{0: float, 1: float, 2: bool}>
     */
    public static function equalsDataProvider(): iterable
    {
        yield 'equals 0 0 true' => [0, 0, true];
        yield 'equals 42 42 true' => [42, 42, true];
        yield 'equals 1 point 0 1 point 0 true' => [1.0, 1.0, true];
        yield 'equals 0 point 0 0 point 0 true' => [0.0, 0.0, true];
        yield 'equals 8 1 point 6 true' => [8 - 6.4, 1.6, true];
        yield 'equals 1 point 6 8 true' => [1.6, 8 - 6.4, true];
        yield 'equals 0 point 0001 0 point 0001 true' => [0.0001, 0.0001, true];
        yield 'equals 0 point 1 0 true' => [0.1 + 0.2 - 0.3, 0, true];
        yield 'equals 0 point 3 0 point 1 true' => [0.3, 0.1 + 0.2, true];
        yield 'equals 0 point 4 0 point 1 true' => [0.4 - 0.1, 0.1 + 0.2, true];
        yield 'equals 1 2 false' => [1, 2, false];
        yield 'equals 1 1 point 0001 false' => [1, 1.0001, false];
        yield 'equals 0 point 00001 0 false' => [0.00001, 0, false];
        yield 'equals -0 point 1 0 point 1 false' => [-0.1, 0.1, false];
        yield 'equals 42 point 00001 42 point 000001 false' => [42.00001, 42.000001, false];
    }

    #[DataProvider('notEqualsDataProvider')]
    public function testNotEquals(float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::notEquals($a, $b));
    }

    /**
     * @return iterable<string, array{0: float, 1: float, 2: bool}>
     */
    public static function notEqualsDataProvider(): iterable
    {
        foreach (self::equalsDataProvider() as $name => $testData) {
            yield $name => [$testData[0], $testData[1], !$testData[2]];
        }
    }

    #[DataProvider('lessThanDataProvider')]
    public function testLessThan(float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::lessThan($a, $b));
    }

    /**
     * @return iterable<string, array{0: float, 1: float, 2: bool}>
     */
    public static function lessThanDataProvider(): iterable
    {
        yield 'integer one is less than integer two' => [1, 2, true];
        yield 'decimal difference above epsilon is treated as less than' => [1, 1.0001, true];
        yield 'zero is less than a positive value above epsilon' => [0, 0.00001, true];
        yield 'computed negative value is less than positive decimal value' => [0 - 0.1, 0.1, true];
        yield 'smaller fractional value is less than larger fractional value' => [42.000001, 42.00001, true];
        yield 'integer zero is not less than itself' => [0, 0, false];
        yield 'integer value is not less than itself' => [42, 42, false];
        yield 'float one is not less than itself' => [1.0, 1.0, false];
        yield 'float zero is not less than itself' => [0.0, 0.0, false];
        yield 'computed decimal equal within epsilon is not less than exact decimal' => [8 - 6.4, 1.6, false];
        yield 'exact decimal is not less than computed decimal equal within epsilon' => [1.6, 8 - 6.4, false];
        yield 'larger decimal value is not less than smaller integer value' => [1.00001, 1, false];
        yield 'positive decimal value is not less than zero' => [0.00001, 0, false];
        yield 'same small decimal value is not less than itself' => [0.0001, 0.0001, false];
        yield 'floating point zero result is not less than zero' => [0.1 + 0.2 - 0.3, 0, false];
        yield 'decimal value equal within epsilon is not less than summed decimals' => [0.3, 0.1 + 0.2, false];
        yield 'computed decimal value equal within epsilon is not less than summed decimals' => [0.4 - 0.1, 0.1 + 0.2, false];
        yield 'repeated addition equal within epsilon is not less than summed decimals' => [0.1 + 0.1 + 0.1, 0.1 + 0.2, false];
    }

    #[DataProvider('greaterThanDataProvider')]
    public function testGreaterThan(float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::greaterThan($a, $b));
    }

    /**
     * @return iterable<string, array{0: float, 1: float, 2: bool}>
     */
    public static function greaterThanDataProvider(): iterable
    {
        yield 'greater than 2 1 true' => [2, 1, true];
        yield 'greater than 1 point 00001 1 true' => [1.00001, 1, true];
        yield 'greater than 0 point 00001 0 true' => [0.00001, 0, true];
        yield 'greater than 0 point 1 0 true' => [0.1, 0 - 0.1, true];
        yield 'greater than 42 point 00001 42 point 000001 true' => [42.00001, 42.000001, true];
        yield 'greater than 0 0 false' => [0, 0, false];
        yield 'greater than 42 42 false' => [42, 42, false];
        yield 'greater than 1 point 0 1 point 0 false' => [1.0, 1.0, false];
        yield 'greater than 0 point 0 0 point 0 false' => [0.0, 0.0, false];
        yield 'greater than 8 1 point 6 false' => [8 - 6.4, 1.6, false];
        yield 'greater than 1 point 6 8 false' => [1.6, 8 - 6.4, false];
        yield 'greater than 1 1 point 0001 false' => [1, 1.0001, false];
        yield 'greater than 0 0 point 00001 false' => [0, 0.00001, false];
        yield 'greater than 0 point 0001 0 point 0001 false' => [0.0001, 0.0001, false];
        yield 'greater than 0 point 1 0 false' => [0.1 + 0.2 - 0.3, 0, false];
        yield 'greater than 0 point 3 0 point 1 false' => [0.3, 0.1 + 0.2, false];
        yield 'greater than 0 point 4 0 point 1 false' => [0.4 - 0.1, 0.1 + 0.2, false];
        yield 'greater than 0 point 1 0 point 1 false' => [0.1 + 0.1 + 0.1, 0.1 + 0.2, false];
    }

    #[DataProvider('lessThanOrEqualsDataProvider')]
    public function testLessThanOrEquals(float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::lessThanOrEquals($a, $b));
    }

    /**
     * @return iterable<string, array{0: float, 1: float, 2: bool}>
     */
    public static function lessThanOrEqualsDataProvider(): iterable
    {
        yield 'less than or equals 0 0 true' => [0, 0, true];
        yield 'less than or equals 42 42 true' => [42, 42, true];
        yield 'less than or equals 1 point 0 1 point 0 true' => [1.0, 1.0, true];
        yield 'less than or equals 0 point 0 0 point 0 true' => [0.0, 0.0, true];
        yield 'less than or equals 8 1 point 6 true' => [8 - 6.4, 1.6, true];
        yield 'less than or equals 1 point 6 8 true' => [1.6, 8 - 6.4, true];
        yield 'less than or equals 1 1 point 0001 true' => [1, 1.0001, true];
        yield 'less than or equals 0 0 point 00001 true' => [0, 0.00001, true];
        yield 'less than or equals 0 point 0001 0 point 0001 true' => [0.0001, 0.0001, true];
        yield 'less than or equals 42 point 0000001 42 point 000001 true' => [42.0000001, 42.000001, true];
        yield 'less than or equals 0 point 1 0 true' => [0.1 + 0.2 - 0.3, 0, true];
        yield 'less than or equals 0 point 3 0 point 1 true' => [0.3, 0.1 + 0.2, true];
        yield 'less than or equals 0 point 4 0 point 1 true' => [0.4 - 0.1, 0.1 + 0.2, true];
        yield 'less than or equals 0 point 1 0 point 1 true' => [0.1 + 0.1 + 0.1, 0.1 + 0.2, true];
        yield 'less than or equals 2 1 false' => [2, 1, false];
        yield 'less than or equals 1 point 00001 1 false' => [1.00001, 1, false];
        yield 'less than or equals 0 point 00001 0 false' => [0.00001, 0, false];
        yield 'less than or equals 0 point 1 0 false' => [0.1, 0 - 0.1, false];
    }

    #[DataProvider('greaterThanOrEqualsDataProvider')]
    public function testGreaterThanOrEquals(float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::greaterThanOrEquals($a, $b));
    }

    /**
     * @return iterable<string, array{0: float, 1: float, 2: bool}>
     */
    public static function greaterThanOrEqualsDataProvider(): iterable
    {
        yield 'greater than or equals 0 0 true' => [0, 0, true];
        yield 'greater than or equals 42 42 true' => [42, 42, true];
        yield 'greater than or equals 1 point 0 1 point 0 true' => [1.0, 1.0, true];
        yield 'greater than or equals 0 point 0 0 point 0 true' => [0.0, 0.0, true];
        yield 'greater than or equals 8 1 point 6 true' => [8 - 6.4, 1.6, true];
        yield 'greater than or equals 1 point 6 8 true' => [1.6, 8 - 6.4, true];
        yield 'greater than or equals 0 point 0001 0 point 0001 true' => [0.0001, 0.0001, true];
        yield 'greater than or equals 42 point 000000001 42 point 00000001 true' => [42.000000001, 42.00000001, true];
        yield 'greater than or equals 0 point 1 0 true' => [0.1 + 0.2 - 0.3, 0, true];
        yield 'greater than or equals 0 point 3 0 point 1 true' => [0.3, 0.1 + 0.2, true];
        yield 'greater than or equals 0 point 4 0 point 1 true' => [0.4 - 0.1, 0.1 + 0.2, true];
        yield 'greater than or equals 0 point 1 0 point 1 true' => [0.1 + 0.1 + 0.1, 0.1 + 0.2, true];
        yield 'greater than or equals 2 1 true' => [2, 1, true];
        yield 'greater than or equals 1 point 00001 1 true' => [1.00001, 1, true];
        yield 'greater than or equals 0 point 00001 0 true' => [0.00001, 0, true];
        yield 'positive value is greater than computed negative value' => [0.1, 0 - 0.1, true];
        yield 'greater than or equals 1 1 point 0001 false' => [1, 1.0001, false];
        yield 'greater than or equals 0 0 point 00001 false' => [0, 0.00001, false];
        yield 'greater than or equals 23 42 false' => [23, 42, false];
    }
}
