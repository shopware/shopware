<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Util\Exception\ComparatorException;
use Shopware\Core\Framework\Util\FloatComparator;

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

    public function testCompareThrowException(): void
    {
        static::expectException(ComparatorException::class);
        $this->expectExceptionMessage(ComparatorException::operatorNotSupported('empty')->getMessage());

        FloatComparator::compare(1, 2, 'empty');
    }

    /**
     * @return iterable<string, array<string, string|float|bool>>
     */
    public static function compareDataProvider(): iterable
    {
        yield 'Test not equal return true' => [
            'operator' => Rule::OPERATOR_NEQ,
            'a' => 1,
            'b' => 2,
            'expected' => true,
        ];
        yield 'Test not equal return false' => [
            'operator' => Rule::OPERATOR_NEQ,
            'a' => 1,
            'b' => 1,
            'expected' => false,
        ];
        yield 'Test greater than or equal return true' => [
            'operator' => Rule::OPERATOR_GTE,
            'a' => 1,
            'b' => 1,
            'expected' => true,
        ];
        yield 'Test greater than or equal return false' => [
            'operator' => Rule::OPERATOR_GTE,
            'a' => 1,
            'b' => 2,
            'expected' => false,
        ];
        yield 'Test less than or equal return true' => [
            'operator' => Rule::OPERATOR_LTE,
            'a' => 1,
            'b' => 1,
            'expected' => true,
        ];
        yield 'Test less than or equal return false' => [
            'operator' => Rule::OPERATOR_LTE,
            'a' => 1,
            'b' => 0,
            'expected' => false,
        ];
        yield 'Test equal return true' => [
            'operator' => Rule::OPERATOR_EQ,
            'a' => 1,
            'b' => 1,
            'expected' => true,
        ];
        yield 'Test equal return false' => [
            'operator' => Rule::OPERATOR_EQ,
            'a' => 1,
            'b' => 2,
            'expected' => false,
        ];
        yield 'Test greater than return true' => [
            'operator' => Rule::OPERATOR_GT,
            'a' => 2,
            'b' => 1,
            'expected' => true,
        ];
        yield 'Test greater than return false' => [
            'operator' => Rule::OPERATOR_GT,
            'a' => 1,
            'b' => 2,
            'expected' => false,
        ];
        yield 'Test less than return true' => [
            'operator' => Rule::OPERATOR_LT,
            'a' => 1,
            'b' => 2,
            'expected' => true,
        ];
        yield 'Test less than return false' => [
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

        /** @phpstan-ignore-next-line phpstan errors because the check is always false, which is exactly the point */
        static::assertFalse($x === 3.155);
        static::assertTrue(FloatComparator::cast($x) === 3.155);
    }

    /**
     * @return array{0: float, 1: float, 2: bool}[]
     */
    public static function equalsDataProvider(): array
    {
        return [
            [0, 0, true],
            [42, 42, true],
            [1.0, 1.0, true],
            [0.0, 0.0, true],
            [8 - 6.4, 1.6, true],
            [1.6, 8 - 6.4, true],
            [0.0001, 0.0001, true],
            [0.1 + 0.2 - 0.3, 0, true],
            [0.3, 0.1 + 0.2, true],
            [0.4 - 0.1, 0.1 + 0.2, true],
            [1, 2, false],
            [1, 1.0001, false],
            [0.00001, 0, false],
            [-0.1, 0.1, false],
            [42.00001, 42.000001, false],
        ];
    }

    #[DataProvider('notEqualsDataProvider')]
    public function testNotEquals(float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::notEquals($a, $b));
    }

    /**
     * @return array{0: float, 1: float, 2: bool}[]
     */
    public static function notEqualsDataProvider(): array
    {
        $equalsData = self::equalsDataProvider();

        return \array_map(
            fn ($testData) => [$testData[0], $testData[1], !$testData[2]],
            $equalsData
        );
    }

    #[DataProvider('lessThanDataProvider')]
    public function testLessThan(float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::lessThan($a, $b));
    }

    /**
     * @return array{0: float, 1: float, 2: bool}[]
     */
    public static function lessThanDataProvider(): array
    {
        return [
            [1, 2, true],
            [1, 1.0001, true],
            [0, 0.00001, true],
            [0 - 0.1, 0.1, true],
            [42.000001, 42.00001, true],
            [0, 0, false],
            [42, 42, false],
            [1.0, 1.0, false],
            [0.0, 0.0, false],
            [0.0, 0.0, false],
            [8 - 6.4, 1.6, false],
            [1.6, 8 - 6.4, false],
            [1.00001, 1, false],
            [0.00001, 0, false],
            [0.0001, 0.0001, false],
            [0.1 + 0.2 - 0.3, 0, false],
            [0.3, 0.1 + 0.2, false],
            [0.4 - 0.1, 0.1 + 0.2, false],
            [0.1 + 0.1 + 0.1, 0.1 + 0.2, false],
        ];
    }

    #[DataProvider('greaterThanDataProvider')]
    public function testGreaterThan(float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::greaterThan($a, $b));
    }

    /**
     * @return array{0: float, 1: float, 2: bool}[]
     */
    public static function greaterThanDataProvider(): array
    {
        return [
            [2, 1, true],
            [1.00001, 1, true],
            [0.00001, 0, true],
            [0.1, 0 - 0.1, true],
            [42.00001, 42.000001, true],
            [0, 0, false],
            [42, 42, false],
            [1.0, 1.0, false],
            [0.0, 0.0, false],
            [8 - 6.4, 1.6, false],
            [1.6, 8 - 6.4, false],
            [1, 1.0001, false],
            [0, 0.00001, false],
            [0.0001, 0.0001, false],
            [0.1 + 0.2 - 0.3, 0, false],
            [0.3, 0.1 + 0.2, false],
            [0.4 - 0.1, 0.1 + 0.2, false],
            [0.1 + 0.1 + 0.1, 0.1 + 0.2, false],
        ];
    }

    #[DataProvider('lessThanOrEqualsDataProvider')]
    public function testLessThanOrEquals(float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::lessThanOrEquals($a, $b));
    }

    /**
     * @return array{0: float, 1: float, 2: bool}[]
     */
    public static function lessThanOrEqualsDataProvider(): array
    {
        return [
            [0, 0, true],
            [42, 42, true],
            [1.0, 1.0, true],
            [0.0, 0.0, true],
            [8 - 6.4, 1.6, true],
            [1.6, 8 - 6.4, true],
            [1, 1.0001, true],
            [0, 0.00001, true],
            [0.0001, 0.0001, true],
            [42.0000001, 42.000001, true],
            [0.1 + 0.2 - 0.3, 0, true],
            [0.3, 0.1 + 0.2, true],
            [0.4 - 0.1, 0.1 + 0.2, true],
            [0.1 + 0.1 + 0.1, 0.1 + 0.2, true],
            [2, 1, false],
            [1.00001, 1, false],
            [0.00001, 0, false],
            [0.1, 0 - 0.1, false],
        ];
    }

    #[DataProvider('greaterThanOrEqualsDataProvider')]
    public function testGreaterThanOrEquals(float $a, float $b, bool $expected): void
    {
        static::assertSame($expected, FloatComparator::greaterThanOrEquals($a, $b));
    }

    /**
     * @return array{0: float, 1: float, 2: bool}[]
     */
    public static function greaterThanOrEqualsDataProvider(): array
    {
        return [
            [0, 0, true],
            [42, 42, true],
            [1.0, 1.0, true],
            [0.0, 0.0, true],
            [8 - 6.4, 1.6, true],
            [1.6, 8 - 6.4, true],
            [0.0001, 0.0001, true],
            [42.000000001, 42.00000001, true],
            [0.1 + 0.2 - 0.3, 0, true],
            [0.3, 0.1 + 0.2, true],
            [0.4 - 0.1, 0.1 + 0.2, true],
            [0.1 + 0.1 + 0.1, 0.1 + 0.2, true],
            [2, 1, true],
            [1.00001, 1, true],
            [0.00001, 0, true],
            [0.1, 0 - 0.1, true],
            [1, 1.0001, false],
            [0, 0.00001, false],
            [23, 42, false],
        ];
    }
}
