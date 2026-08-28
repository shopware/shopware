<?php declare(strict_types=1);

namespace Shopware\Core\Test\Assert;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\LogicalNot;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Constraint\StrictIsEmpty;

/**
 * @internal
 */
#[Package('framework')]
final class StrictEmpty
{
    public static function assertEmpty(mixed $actual, string $message = ''): void
    {
        Assert::assertThat($actual, new StrictIsEmpty(), $message);
    }

    public static function assertNotEmpty(mixed $actual, string $message = ''): void
    {
        Assert::assertThat($actual, new LogicalNot(new StrictIsEmpty()), $message);
    }
}
