<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\BeforeCartMergeEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsSubscriber;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(CartPromotionsSubscriber::class)]
#[Package('checkout')]
class CartPromotionsSubscriberTest extends TestCase
{
    #[Group('promotions')]
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([BeforeCartMergeEvent::class => 'onBeforeCartMerge'], CartPromotionsSubscriber::getSubscribedEvents());
    }

    #[Group('promotions')]
    public function testOnBeforeCartMergesPromotions(): void
    {
        $guestPromotions = new CartExtension();
        $guestPromotions->addCode('tenpercent');

        $guestCart = new Cart('guest');
        $guestCart->addExtension(CartExtension::KEY, $guestPromotions);

        $customerPromotions = new CartExtension();
        $customerPromotions->addCode('twentypercent');

        $customerCart = new Cart('customer');
        $customerCart->addExtension(CartExtension::KEY, $customerPromotions);

        $event = new BeforeCartMergeEvent($customerCart, $guestCart, new LineItemCollection(), Generator::generateSalesChannelContext());

        $subscriber = new CartPromotionsSubscriber();
        $subscriber->onBeforeCartMerge($event);

        $customerCart = $event->getCustomerCart();

        static::assertTrue($customerCart->hasExtension(CartExtension::KEY));

        /** @var CartExtension $customerPromotions */
        $customerPromotions = $customerCart->getExtension(CartExtension::KEY);

        static::assertCount(2, $customerPromotions->getCodes());
        static::assertTrue($customerPromotions->hasCode('tenpercent'));
        static::assertTrue($customerPromotions->hasCode('twentypercent'));
        static::assertFalse($customerPromotions->hasCode('thrirtypercent'));
    }

    #[Group('promotions')]
    public function testMergeDoesNotDuplicatePromotions(): void
    {
        $guestPromotions = new CartExtension();
        $guestPromotions->addCode('tenpercent');

        $guestCart = new Cart('guest');
        $guestCart->addExtension(CartExtension::KEY, $guestPromotions);

        $customerPromotions = new CartExtension();
        $customerPromotions->addCode('tenpercent');

        $customerCart = new Cart('customer');
        $customerCart->addExtension(CartExtension::KEY, $customerPromotions);

        $event = new BeforeCartMergeEvent($customerCart, $guestCart, new LineItemCollection(), Generator::generateSalesChannelContext());

        $subscriber = new CartPromotionsSubscriber();
        $subscriber->onBeforeCartMerge($event);

        $customerCart = $event->getCustomerCart();

        static::assertTrue($customerCart->hasExtension(CartExtension::KEY));

        /** @var CartExtension $customerPromotions */
        $customerPromotions = $customerCart->getExtension(CartExtension::KEY);

        static::assertCount(1, $customerPromotions->getCodes());
        static::assertTrue($customerPromotions->hasCode('tenpercent'));
        static::assertFalse($customerPromotions->hasCode('tenPercent'));
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[Group('promotions')]
    #[DisabledFeatures(['PERMANENT_AUTOMATIC_PROMOTIONS'])]
    public function testOnBeforeCartMergesBlockedPromotionsWhenFeatureDisabled(): void
    {
        $guestPromotions = new CartExtension();
        $guestPromotions->addCode('tenpercent');
        $guestPromotions->blockPromotion('foo');

        $guestCart = new Cart('guest');
        $guestCart->addExtension(CartExtension::KEY, $guestPromotions);

        $customerPromotions = new CartExtension();
        $customerPromotions->addCode('twentypercent');
        $customerPromotions->blockPromotion('bar');

        $customerCart = new Cart('customer');
        $customerCart->addExtension(CartExtension::KEY, $customerPromotions);

        $event = new BeforeCartMergeEvent($customerCart, $guestCart, new LineItemCollection(), Generator::generateSalesChannelContext());

        $subscriber = new CartPromotionsSubscriber();
        $subscriber->onBeforeCartMerge($event);

        /** @var CartExtension $mergedPromotions */
        $mergedPromotions = $event->getCustomerCart()->getExtension(CartExtension::KEY);

        static::assertTrue($mergedPromotions->isPromotionBlocked('foo'));
        static::assertTrue($mergedPromotions->isPromotionBlocked('bar'));
        static::assertFalse($mergedPromotions->isPromotionBlocked('baz'));
    }

    #[Group('promotions')]
    public function testSkipsExecutionIfGuestPromotionsAreEmpty(): void
    {
        $guestCart = new Cart('guest');

        $customerPromotions = new CartExtension();
        $customerPromotions->addCode('twentypercent');

        $customerCart = new Cart('customer');
        $customerCart->addExtension(CartExtension::KEY, $customerPromotions);

        $event = new BeforeCartMergeEvent($customerCart, $guestCart, new LineItemCollection(), Generator::generateSalesChannelContext());

        $subscriber = new CartPromotionsSubscriber();
        $subscriber->onBeforeCartMerge($event);

        $customerCart = $event->getCustomerCart();

        static::assertTrue($customerCart->hasExtension(CartExtension::KEY));

        /** @var CartExtension $customerPromotions */
        $customerPromotions = $customerCart->getExtension(CartExtension::KEY);

        static::assertCount(1, $customerPromotions->getCodes());

        static::assertTrue($customerPromotions->hasCode('twentypercent'));
    }
}
