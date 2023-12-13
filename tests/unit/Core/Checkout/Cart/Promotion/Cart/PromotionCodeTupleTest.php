<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCodeTuple;
use Shopware\Core\Checkout\Promotion\PromotionEntity;

/**
 * @internal
 */
#[CoversClass(PromotionCodeTuple::class)]
class PromotionCodeTupleTest extends TestCase
{
    /**
     * This test verifies that our code is correctly
     * assigned in the tuple and our getter
     * does return that value.
     */
    #[Group('promotions')]
    public function testCode(): void
    {
        $promotion1 = new PromotionEntity();

        $tuple = new PromotionCodeTuple('codeA', $promotion1);

        static::assertEquals('codeA', $tuple->getCode());
    }

    /**
     * This test verifies that our promotion is correctly
     * assigned in the tuple and our getter
     * does return that object.
     */
    #[Group('promotions')]
    public function testPromotion(): void
    {
        $promotion1 = new PromotionEntity();

        $tuple = new PromotionCodeTuple('codeA', $promotion1);

        static::assertSame($promotion1, $tuple->getPromotion());
    }
}
