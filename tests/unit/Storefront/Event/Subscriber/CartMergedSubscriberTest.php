<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Event\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\CartMergedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Event\CartMergedSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('storefront')]
#[CoversClass(CartMergedSubscriber::class)]
class CartMergedSubscriberTest extends TestCase
{
    public function testMergedHintIsAdded(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(static::once())
            ->method('trans')
            ->with('checkout.cart-merged-hint')
            ->willReturn('checkout.cart-merged-hint');

        $subscriber = new CartMergedSubscriber($translator, $requestStack);

        $cartMergedEvent = $this->createCartMergedEvent();

        $subscriber->addCartMergedNoticeFlash($cartMergedEvent);

        static::assertNotEmpty($infoFlash = $session->getFlashBag()->get('info'));
        static::assertEquals('checkout.cart-merged-hint', $infoFlash[0]);
    }

    public function testGetSubscribedEventsReturnsAddCartMergedNoticeFlash(): void
    {
        static::assertEquals(
            [CartMergedEvent::class => 'addCartMergedNoticeFlash'],
            CartMergedSubscriber::getSubscribedEvents()
        );
    }

    public function testMergedSubscriberDoNothingWithoutSessionAssigned(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(static::never())->method('trans');

        $subscriber = new CartMergedSubscriber($translator, $requestStack);

        $cartMergedEvent = $this->createCartMergedEvent();

        $subscriber->addCartMergedNoticeFlash($cartMergedEvent);

        static::assertEmpty($session->getFlashBag()->get('info'));
    }

    public function testMergedSubscriberDoNothingWithEmptyRequestStack(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $requestStack = new RequestStack();

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(static::never())->method('trans');

        $subscriber = new CartMergedSubscriber($translator, $requestStack);

        $cartMergedEvent = $this->createCartMergedEvent();

        $subscriber->addCartMergedNoticeFlash($cartMergedEvent);

        static::assertEmpty($session->getFlashBag()->get('info'));
    }

    public function testMergedSubscriberDoNothingWithIncompatibleSession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(static::never())->method('trans');

        $subscriber = new CartMergedSubscriber($translator, $requestStack);

        $cartMergedEvent = $this->createCartMergedEvent();

        $subscriber->addCartMergedNoticeFlash($cartMergedEvent);
    }

    private function createCartMergedEvent(): CartMergedEvent
    {
        $currentContextToken = 'currentToken';
        $currentContext = Generator::createSalesChannelContext(token: $currentContextToken);

        // Create Guest cart
        $previousCart = new Cart($currentContextToken);

        $productId1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();

        $productLineItem1 = new LineItem($productId1, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId1);
        $productLineItem2 = new LineItem($productId2, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId2);
        $productLineItem1->setStackable(true);
        $productLineItem2->setStackable(true);
        $productLineItem1->setQuantity(1);
        $productLineItem2->setQuantity(5);

        $previousCart->addLineItems(new LineItemCollection([$productLineItem1, $productLineItem2]));
        $previousCart->markUnmodified();

        return new CartMergedEvent(new Cart('customerToken'), $currentContext, $previousCart);
    }
}
