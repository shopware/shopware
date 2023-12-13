<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Aggregate\PromotionDiscount;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;

/**
 * @internal
 */
#[CoversClass(PromotionDiscountEntity::class)]
class PromotionDiscountEntityTest extends TestCase
{
    /**
     * This test verifies that our constant for the
     * cart scope is not touched without recognizing it.
     */
    #[Group('promotions')]
    public function testScopeCart(): void
    {
        static::assertEquals('cart', PromotionDiscountEntity::SCOPE_CART);
    }

    /**
     * This test verifies that our constant for the
     * delivery scope is not touched without recognizing it.
     */
    #[Group('promotions')]
    public function testScopeDelivery(): void
    {
        static::assertEquals('delivery', PromotionDiscountEntity::SCOPE_DELIVERY);
    }

    /**
     * This test verifies that our constant for the
     * set scope is not touched without recognizing it.
     */
    #[Group('promotions')]
    public function testScopeSet(): void
    {
        static::assertEquals('set', PromotionDiscountEntity::SCOPE_SET);
    }

    /**
     * This test verifies that our constant for the
     * setgroup scope is not touched without recognizing it.
     */
    #[Group('promotions')]
    public function testScopeSetGroup(): void
    {
        static::assertEquals('setgroup', PromotionDiscountEntity::SCOPE_SETGROUP);
    }

    /**
     * This test verifies that our constant for the
     * absolute type is not touched without recognizing it.
     */
    #[Group('promotions')]
    public function testTypeAbsolute(): void
    {
        static::assertEquals('absolute', PromotionDiscountEntity::TYPE_ABSOLUTE);
    }

    /**
     * This test verifies that our constant for the
     * percentage type is not touched without recognizing it.
     */
    #[Group('promotions')]
    public function testTypePercentage(): void
    {
        static::assertEquals('percentage', PromotionDiscountEntity::TYPE_PERCENTAGE);
    }

    /**
     * This test verifies that our constant for the
     * fixed type is not touched without recognizing it.
     */
    #[Group('promotions')]
    public function testTypeFixed(): void
    {
        static::assertEquals('fixed', PromotionDiscountEntity::TYPE_FIXED);
    }

    /**
     * This test verifies that our constant for the
     * fixed unit type is not touched without recognizing it.
     */
    #[Group('promotions')]
    public function testTypeFixedUnit(): void
    {
        static::assertEquals('fixed_unit', PromotionDiscountEntity::TYPE_FIXED_UNIT);
    }
}
