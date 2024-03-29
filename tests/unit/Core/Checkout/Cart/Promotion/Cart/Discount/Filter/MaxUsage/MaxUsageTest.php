<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Discount\Filter\MaxUsage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\MaxUsage\MaxUsage;

/**
 * @internal
 */
#[CoversClass(MaxUsage::class)]
class MaxUsageTest extends TestCase
{
    /**
     * This test verifies that we get -1
     * which means unlimited if we have a cart (1 package)
     * without restrictions.
     */
    #[Group('promotions')]
    public function testCartUnlimited(): void
    {
        $usage = new MaxUsage();

        $items = $usage->getMaxItemCount(
            'ALL',
            'ALL',
            1
        );

        static::assertEquals(-1, $items);
    }

    /**
     * This test verifies that we get max 2 items
     * if we have a cart (1 package) without appliers.
     */
    #[Group('promotions')]
    public function testCartMax2(): void
    {
        $usage = new MaxUsage();

        $items = $usage->getMaxItemCount(
            'ALL',
            '2',
            1
        );

        static::assertEquals(2, $items);
    }

    /**
     * This test verifies that we get only
     * the second item (1x) if we have a cart (1 package)
     * and use an applier.
     */
    #[Group('promotions')]
    public function testCartApplier(): void
    {
        $usage = new MaxUsage();

        $items = $usage->getMaxItemCount(
            '2',
            'ALL',
            1
        );

        static::assertEquals(1, $items);
    }

    /**
     * This test verifies that we get only
     * the second item (1x) if we have a cart (1 package)
     * and use an applier.
     */
    #[Group('promotions')]
    public function testCartApplier2(): void
    {
        $usage = new MaxUsage();

        $items = $usage->getMaxItemCount(
            '2',
            '1',
            1
        );

        static::assertEquals(1, $items);
    }

    /**
     * This test verifies that we get
     * unlimited items if we have no limitations
     * and also multiple packages and groups.
     */
    #[Group('promotions')]
    public function testMultipleGroupsUnlimited(): void
    {
        $usage = new MaxUsage();

        $items = $usage->getMaxItemCount(
            'ALL',
            'ALL',
            3
        );

        static::assertEquals(-1, $items);
    }
}
