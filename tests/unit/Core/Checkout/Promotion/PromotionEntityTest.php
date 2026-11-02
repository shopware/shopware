<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\Feature;
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

        static::assertTrue(self::isOrderCountPerCustomerCountValid($promotion, 'customer-id'));

        $promotion->setMaxRedemptionsPerCustomer(2);

        static::assertTrue(self::isOrderCountPerCustomerCountValid($promotion, 'customer-id'));
    }

    public function testOrderCountPerCustomerComparesTheCustomersOwnCount(): void
    {
        $promotion = new PromotionEntity();
        $promotion->setMaxRedemptionsPerCustomer(2);
        Feature::silent('v6.8.0.0', static fn () => $promotion->setOrdersPerCustomerCount([
            'customer-a' => 2,
            'customer-b' => 1,
        ]));

        static::assertFalse(self::isOrderCountPerCustomerCountValid($promotion, 'customer-a'));
        static::assertTrue(self::isOrderCountPerCustomerCountValid($promotion, 'customer-b'));
        static::assertTrue(self::isOrderCountPerCustomerCountValid($promotion, 'customer-without-orders'));
    }

    public function testOrderCountPerCustomerMatchesTheCustomerIdCaseInsensitively(): void
    {
        $promotion = new PromotionEntity();
        $promotion->setMaxRedemptionsPerCustomer(1);
        Feature::silent('v6.8.0.0', static fn () => $promotion->setOrdersPerCustomerCount(['customer-a' => 1]));

        static::assertFalse(self::isOrderCountPerCustomerCountValid($promotion, 'CUSTOMER-A'));
    }

    private static function isOrderCountPerCustomerCountValid(PromotionEntity $promotion, string $customerId): bool
    {
        return Feature::silent('v6.8.0.0', static fn (): bool => $promotion->isOrderCountPerCustomerCountValid($customerId));
    }
}
