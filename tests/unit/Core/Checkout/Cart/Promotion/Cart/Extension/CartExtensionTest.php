<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(CartExtension::class)]
#[Package('checkout')]
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

        static::assertSame(['c123'], $extension->getCodes());
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

        static::assertSame(['c456'], $extension->getCodes());
    }

    #[Group('promotions')]
    public function testMerge(): void
    {
        $extension1 = new CartExtension();
        $extension1->addCode('c123');
        $extension1->blockPromotion('p123');

        $extension2 = new CartExtension();
        $extension2->addCode('c456');
        $extension2->blockPromotion('p456');

        $merged = $extension1->merge($extension2);

        static::assertEquals(['c123', 'c456'], $merged->getCodes());
        static::assertTrue($merged->isPromotionBlocked('p123'));
        static::assertTrue($merged->isPromotionBlocked('p456'));
    }

    #[Group('promotions')]
    public function testMergeCreatesImmutable(): void
    {
        $extension1 = new CartExtension();
        $extension1->addCode('c123');
        $extension1->blockPromotion('p123');

        $extension2 = new CartExtension();
        $extension2->addCode('c456');
        $extension2->blockPromotion('p456');

        $merged = $extension1->merge($extension2);

        static::assertNotSame($extension1, $merged);
        static::assertNotSame($extension2, $merged);
    }

    #[Group('promotions')]
    public function testMergeKillsDuplicates(): void
    {
        $extension1 = new CartExtension();
        $extension1->addCode('c123');
        $extension1->blockPromotion('p123');

        $extension2 = new CartExtension();
        $extension2->addCode('c123'); // Duplicate code
        $extension2->blockPromotion('p456');

        $merged = $extension1->merge($extension2);

        static::assertEquals(['c123'], $merged->getCodes());
        static::assertTrue($merged->isPromotionBlocked('p123'));
        static::assertTrue($merged->isPromotionBlocked('p456'));
    }
}
