<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Util\ArrayComparator;
use Shopware\Core\Framework\Util\Exception\ComparatorException;

/**
 * @internal
 */
#[CoversClass(ArrayComparator::class)]
class ArrayComparatorTest extends TestCase
{
    /**
     * @param array<string|int|bool|float> $a
     * @param array<string|int|bool|float> $b
     */
    #[DataProvider('compareDataProvider')]
    public function testCompare(string $operator, array $a, array $b, bool $expected): void
    {
        static::assertSame($expected, ArrayComparator::compare($a, $b, $operator));
    }

    /**
     * @param array<string|int|bool|float> $a
     * @param array<string|int|bool|float> $b
     */
    #[DataProvider('equalsDataProvider')]
    public function testEquals(array $a, array $b, bool $expected): void
    {
        static::assertSame($expected, ArrayComparator::equals($a, $b));
    }

    /**
     * @param array<string|int|bool|float> $a
     * @param array<string|int|bool|float> $b
     */
    #[DataProvider('notEqualsDataProvider')]
    public function testNotEquals(array $a, array $b, bool $expected): void
    {
        static::assertSame($expected, ArrayComparator::notEquals($a, $b));
    }

    public function testCompareThrowException(): void
    {
        static::expectException(ComparatorException::class);
        $this->expectExceptionMessage(ComparatorException::operatorNotSupported('>')->getMessage());

        ArrayComparator::compare([1], [2], '>');
    }

    /**
     * @return iterable<string, array<string, string|bool|array<int>>>
     */
    public static function compareDataProvider(): iterable
    {
        yield 'Test compare not equal return true' => [
            'operator' => Rule::OPERATOR_NEQ,
            'a' => [1, 2],
            'b' => [3],
            'expected' => true,
        ];
        yield 'Test compare not equal return false' => [
            'operator' => Rule::OPERATOR_NEQ,
            'a' => [1, 2],
            'b' => [1, 2],
            'expected' => false,
        ];
        yield 'Test compare equal return false' => [
            'operator' => Rule::OPERATOR_EQ,
            'a' => [1, 2],
            'b' => [3],
            'expected' => false,
        ];
        yield 'Test compare equal return true' => [
            'operator' => Rule::OPERATOR_EQ,
            'a' => [1, 2],
            'b' => [1, 2],
            'expected' => true,
        ];
    }

    /**
     * @return iterable<string, array<string, bool|array<int>>>
     */
    public static function equalsDataProvider(): iterable
    {
        yield 'Two completely different arrays return false' => [
            'a' => [1, 2],
            'b' => [3],
            'expected' => false,
        ];
        yield 'two identical arrays return true' => [
            'a' => [1, 2],
            'b' => [1, 2],
            'expected' => true,
        ];
        yield 'The first array contains the second array return true' => [
            'a' => [1, 2],
            'b' => [1],
            'expected' => true,
        ];
        yield 'The second array contain the first array return true' => [
            'a' => [1],
            'b' => [1, 3],
            'expected' => true,
        ];
        yield 'One element in the first array exists in the second array return true' => [
            'a' => [1, 2, 3],
            'b' => [1, 4, 5],
            'expected' => true,
        ];
        yield 'One element in the second array exists in the first array return true' => [
            'a' => [1, 4, 5],
            'b' => [1, 2, 3],
            'expected' => true,
        ];
        yield 'Some element in the first array exists in the second array return true' => [
            'a' => [1, 2, 3],
            'b' => [1, 3, 4, 5],
            'expected' => true,
        ];
        yield 'Some element in the second array exists in the first array return true' => [
            'a' => [1, 3, 4, 5],
            'b' => [1, 2, 3],
            'expected' => true,
        ];
    }

    /**
     * @return iterable<string, array<string, bool|array<int>>>
     */
    public static function notEqualsDataProvider(): iterable
    {
        yield 'Two completely different arrays return true' => [
            'a' => [1, 2],
            'b' => [3],
            'expected' => true,
        ];
        yield 'two identical arrays return false' => [
            'a' => [1, 2],
            'b' => [1, 2],
            'expected' => false,
        ];
        yield 'The first array contains the second array return false' => [
            'a' => [1, 2],
            'b' => [1],
            'expected' => false,
        ];
        yield 'The second array contain the first array return false' => [
            'a' => [1],
            'b' => [1, 3],
            'expected' => false,
        ];
        yield 'One element in the first array exists in the second array return false' => [
            'a' => [1, 2, 3],
            'b' => [1, 4, 5],
            'expected' => false,
        ];
        yield 'One element in the second array exists in the first array return false' => [
            'a' => [1, 4, 5],
            'b' => [1, 2, 3],
            'expected' => false,
        ];
        yield 'Some element in the first array exists in the second array return false' => [
            'a' => [1, 2, 3],
            'b' => [1, 3, 4, 5],
            'expected' => false,
        ];
        yield 'Some element in the second array exists in the first array return false' => [
            'a' => [1, 3, 4, 5],
            'b' => [1, 2, 3],
            'expected' => false,
        ];
    }
}
