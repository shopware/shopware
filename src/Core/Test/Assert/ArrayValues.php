<?php declare(strict_types=1);

namespace Shopware\Core\Test\Assert;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class ArrayValues
{
    /**
     * @param array<int|string, mixed> $expected
     * @param array<int|string, mixed> $actual
     */
    public static function assertValues(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            TestCase::assertArrayHasKey($key, $actual);

            if (\is_array($value)) {
                self::assertValues($value, $actual[$key]);
            } else {
                TestCase::assertSame($value, $actual[$key]);
            }
        }
    }
}
