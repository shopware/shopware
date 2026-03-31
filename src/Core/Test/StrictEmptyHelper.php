<?php declare(strict_types=1);

namespace Shopware\Core\Test;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\LogicalNot;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Constraint\StrictIsEmpty;

/**
 * @internal
 */
#[Package('framework')]
final class StrictEmptyHelper
{
    /**
     * Asserts that the given value is strictly empty according to the
     * StrictIsEmpty constraint.
     *
     * The strict emptiness rules are implemented by the `StrictIsEmpty`
     * constraint and may differ from PHP's loose "empty" semantics.
     */
    public function assertStrictEmpty(mixed $actual, string $message = ''): void
    {
        Assert::assertThat($actual, new StrictIsEmpty(), $message);
    }

    /**
     * Asserts that the given value is not strictly empty according to the
     * StrictIsEmpty constraint.
     *
     * This is the inverse of `assertStrictEmpty` and uses a logical NOT
     * around the `StrictIsEmpty` constraint to verify the value is considered
     * non-empty by the strict rules.
     */
    public function assertNotStrictEmpty(mixed $actual, string $message = ''): void
    {
        Assert::assertThat($actual, new LogicalNot(new StrictIsEmpty()), $message);
    }
}
