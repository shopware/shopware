<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Assert;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Assert\ArrayValues;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ArrayValues::class)]
class ArrayValuesTest extends TestCase
{
    /**
     * @param array<int|string, mixed> $expected
     * @param array<int|string, mixed> $actual
     */
    #[DataProvider('matchingProvider')]
    #[TestDox('accepts $_dataName')]
    public function testAcceptsMatchingValues(array $expected, array $actual): void
    {
        ArrayValues::assertValues($expected, $actual);
    }

    public static function matchingProvider(): \Generator
    {
        yield 'identical arrays' => [['a' => 1, 'b' => 'x'], ['a' => 1, 'b' => 'x']];
        yield 'a subset of the actual keys' => [['a' => 1], ['a' => 1, 'b' => 2]];
        yield 'a nested subset' => [['a' => ['b' => 1]], ['a' => ['b' => 1, 'c' => 2], 'd' => 3]];
    }

    /**
     * @param array<int|string, mixed> $expected
     * @param array<int|string, mixed> $actual
     */
    #[DataProvider('mismatchProvider')]
    #[TestDox('rejects $_dataName')]
    public function testRejectsMismatchingValues(array $expected, array $actual): void
    {
        $this->expectException(AssertionFailedError::class);

        ArrayValues::assertValues($expected, $actual);
    }

    public static function mismatchProvider(): \Generator
    {
        yield 'a missing key' => [['a' => 1], ['b' => 1]];
        yield 'a different value' => [['a' => 1], ['a' => 2]];
        yield 'a loosely equal value' => [['a' => 1], ['a' => '1']];
        yield 'a missing nested key' => [['a' => ['b' => 1]], ['a' => ['c' => 1]]];
        yield 'a different nested value' => [['a' => ['b' => 1]], ['a' => ['b' => 2]]];
    }
}
