<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionEntity::class)]
class PromotionEntityTest extends TestCase
{
    public function testOrderCountIsValidWithoutAGlobalRedemptionLimit(): void
    {
        $promotion = new PromotionEntity();
        $promotion->setOrderCount(10);

        static::assertTrue($promotion->isOrderCountValid());

        $promotion->setMaxRedemptionsGlobal(0);

        static::assertTrue($promotion->isOrderCountValid());
    }

    public function testOrderCountIsOnlyValidBelowTheGlobalRedemptionLimit(): void
    {
        $promotion = new PromotionEntity();
        $promotion->setMaxRedemptionsGlobal(3);

        $promotion->setOrderCount(2);
        static::assertTrue($promotion->isOrderCountValid());

        $promotion->setOrderCount(3);
        static::assertFalse($promotion->isOrderCountValid());
    }

    public function testOrderCountPerCustomerIsValidWithoutALimitOrWithoutCounts(): void
    {
        $promotion = new PromotionEntity();

        static::assertTrue($promotion->isOrderCountPerCustomerCountValid('customer-id'));

        $promotion->setMaxRedemptionsPerCustomer(2);

        static::assertTrue($promotion->isOrderCountPerCustomerCountValid('customer-id'));
    }

    public function testOrderCountPerCustomerComparesTheCustomersOwnCount(): void
    {
        $promotion = new PromotionEntity();
        $promotion->setMaxRedemptionsPerCustomer(2);
        $promotion->setOrdersPerCustomerCount([
            'customer-a' => 2,
            'customer-b' => 1,
        ]);

        static::assertFalse($promotion->isOrderCountPerCustomerCountValid('customer-a'));
        static::assertTrue($promotion->isOrderCountPerCustomerCountValid('customer-b'));
        static::assertTrue($promotion->isOrderCountPerCustomerCountValid('customer-without-orders'));
    }

    public function testOrderCountPerCustomerMatchesTheCustomerIdCaseInsensitively(): void
    {
        $promotion = new PromotionEntity();
        $promotion->setMaxRedemptionsPerCustomer(1);
        $promotion->setOrdersPerCustomerCount(['customer-a' => 1]);

        static::assertFalse($promotion->isOrderCountPerCustomerCountValid('CUSTOMER-A'));
    }
}
