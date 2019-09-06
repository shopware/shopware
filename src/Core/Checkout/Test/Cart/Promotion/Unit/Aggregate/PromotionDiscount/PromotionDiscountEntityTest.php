<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Aggregate\PromotionDiscount;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;

class PromotionDiscountEntityTest extends TestCase
{
    /**
     * This test verifies that our constant for the
     * cart scope is not touched without recognizing it.
     *
     * @test
     * @group promotions
     */
    public function testScopeCart(): void
    {
        static::assertEquals('cart', PromotionDiscountEntity::SCOPE_CART);
    }

    /**
     * This test verifies that our constant for the
     * absolute type is not touched without recognizing it.
     *
     * @test
     * @group promotions
     */
    public function testTypeAbsolute(): void
    {
        static::assertEquals('absolute', PromotionDiscountEntity::TYPE_ABSOLUTE);
    }

    /**
     * This test verifies that our constant for the
     * percentage type is not touched without recognizing it.
     *
     * @test
     * @group promotions
     */
    public function testTypePercentage(): void
    {
        static::assertEquals('percentage', PromotionDiscountEntity::TYPE_PERCENTAGE);
    }

    /**
     * This test verifies that our constant for the
     * fixed type is not touched without recognizing it.
     *
     * @test
     * @group promotions
     */
    public function testTypeFixed(): void
    {
        static::assertEquals('fixed', PromotionDiscountEntity::TYPE_FIXED);
    }
}
