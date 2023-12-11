<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;

/**
 * @internal
 */
#[CoversClass(CartExtension::class)]
class CartExtensionTest extends TestCase
{
    /**
     * This test verifies that we can add a promotion
     * id and it will be found as "blocked" in the extension
     */
    #[Group('promotions')]
    public function testPromotionIsBlocked(): void
    {
        $extension = new CartExtension();
        $extension->blockPromotion('abc');

        static::assertTrue($extension->isPromotionBlocked('abc'));
    }

    /**
     * This test verifies that a non-existing id
     * is being returned as "not blocked"
     */
    #[Group('promotions')]
    public function testDifferentPromotionIsNotBlocked(): void
    {
        $extension = new CartExtension();

        static::assertFalse($extension->isPromotionBlocked('eef'));
    }

    /**
     * This test verifies that we can add
     * a new code to the extension
     */
    #[Group('promotions')]
    public function testAddCode(): void
    {
        $extension = new CartExtension();
        $extension->addCode('c123');

        static::assertEquals(['c123'], $extension->getCodes());
    }

    /**
     * This test verifies that our function
     * returns the correct value if existing
     */
    #[Group('promotions')]
    public function testHasCode(): void
    {
        $extension = new CartExtension();
        $extension->addCode('c123');

        static::assertTrue($extension->hasCode('c123'));
    }

    /**
     * This test verifies that we can remove
     * an existing code from the cart extension
     */
    #[Group('promotions')]
    public function testRemoveCode(): void
    {
        $extension = new CartExtension();
        $extension->addCode('c123');
        $extension->addCode('c456');

        $extension->removeCode('c123');

        static::assertEquals(['c456'], $extension->getCodes());
    }
}
