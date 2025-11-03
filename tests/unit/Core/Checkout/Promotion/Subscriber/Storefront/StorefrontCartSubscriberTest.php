<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Subscriber\Storefront;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemAddedEvent;
use Shopware\Core\Checkout\Cart\Event\BeforeLineItemRemovedEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Extension\CartExtension;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Subscriber\Storefront\StorefrontCartSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(StorefrontCartSubscriber::class)]
class StorefrontCartSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        $events = StorefrontCartSubscriber::getSubscribedEvents();
        static::assertArrayHasKey(BeforeLineItemAddedEvent::class, $events);
        static::assertArrayHasKey(BeforeLineItemRemovedEvent::class, $events);
        static::assertArrayHasKey(CheckoutOrderPlacedEvent::class, $events);
    }

    public function testResetCodesNoMainRequest(): void
    {
        $requestStack = new RequestStack();
        $subscriber = $this->createSubscriber(null, $requestStack);
        $subscriber->resetCodes();

        static::expectNotToPerformAssertions();
    }

    public function testResetCodesNoSession(): void
    {
        $mainRequest = new Request();
        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);
        $subscriber = $this->createSubscriber(null, $requestStack);
        $subscriber->resetCodes();

        static::expectNotToPerformAssertions();
    }

    public function testResetCodesWithSession(): void
    {
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('set')
            ->with(StorefrontCartSubscriber::SESSION_KEY_PROMOTION_CODES, []);

        $mainRequest = new Request();
        $mainRequest->setSession($session);

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        $subscriber = $this->createSubscriber(null, $requestStack);
        $subscriber->resetCodes();
    }

    public function testOnLineItemAddedNotPromotion(): void
    {
        $cart = Generator::createCart();
        $lineItem = new LineItem('id', 'not-promotion');
        $event = new BeforeLineItemAddedEvent($lineItem, $cart, Generator::generateSalesChannelContext());
        $subscriber = $this->createSubscriber();
        $subscriber->onLineItemAdded($event);

        static::assertEmpty($cart->getExtensions());
    }

    public function testOnLineItemAddedPromotionNoCode(): void
    {
        $cart = Generator::createCart();
        $lineItem = new LineItem('id', PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem->setReferencedId('');
        $event = new BeforeLineItemAddedEvent($lineItem, $cart, Generator::generateSalesChannelContext());
        $subscriber = $this->createSubscriber();
        $subscriber->onLineItemAdded($event);

        static::assertEmpty($cart->getExtensions());
    }

    public function testOnLineItemAddedPromotionWithCode(): void
    {
        $cart = Generator::createCart();
        $lineItem = new LineItem('id', PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem->setReferencedId('CODE123');
        $event = new BeforeLineItemAddedEvent($lineItem, $cart, Generator::generateSalesChannelContext());

        $subscriber = $this->createSubscriber();
        $subscriber->onLineItemAdded($event);

        $extension = $cart->getExtensionOfType(CartExtension::KEY, CartExtension::class);

        static::assertInstanceOf(CartExtension::class, $extension);
        static::assertTrue($extension->hasCode('CODE123'));
    }

    public function testOnLineItemRemovedNotPromotion(): void
    {
        $cart = new Cart('token');
        $lineItem = new LineItem('id', 'not-promotion');
        $cart->add($lineItem);

        $event = new BeforeLineItemRemovedEvent($lineItem, $cart, Generator::generateSalesChannelContext());
        $subscriber = $this->createSubscriber();
        $subscriber->onLineItemRemoved($event);

        static::assertTrue($cart->has($lineItem->getId()));
    }

    public function testOnLineItemRemovedPromotionWithCode(): void
    {
        $cart = Generator::createCart();

        $lineItem = new LineItem('id', PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem->setReferencedId('CODE123');
        $lineItem->setRemovable(true);

        $otherLineItem = new LineItem('otherid', PromotionProcessor::LINE_ITEM_TYPE);
        $otherLineItem->setReferencedId('CODE123');
        $otherLineItem->setRemovable(true);

        $cart->add($otherLineItem);

        $cart->addExtension(CartExtension::KEY, new CartExtension());
        $extension = $cart->getExtensionOfType(CartExtension::KEY, CartExtension::class);
        static::assertInstanceOf(CartExtension::class, $extension);
        $extension->addCode('CODE123');
        $context = Generator::generateSalesChannelContext();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(BeforeLineItemRemovedEvent::class));

        $subscriber = $this->createSubscriber($eventDispatcher);

        $event = new BeforeLineItemRemovedEvent($lineItem, $cart, $context);
        $subscriber->onLineItemRemoved($event);

        static::assertFalse($cart->has($otherLineItem->getId()));
        static::assertFalse($extension->hasCode('CODE123'));
    }

    public function testOnLineItemRemovedPromotionWithCodeNoDiscountType(): void
    {
        $cart = Generator::createCart();
        $lineItem = new LineItem('id', PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem->setReferencedId('CODE123');
        $lineItem->setRemovable(true);
        $cart->add($lineItem);
        $cart->addExtension(CartExtension::KEY, new CartExtension());
        $context = Generator::generateSalesChannelContext();

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $subscriber = $this->createSubscriber($eventDispatcher);
        $event = new BeforeLineItemRemovedEvent($lineItem, $cart, $context);
        $subscriber->onLineItemRemoved($event);

        $extension = $cart->getExtensionOfType(CartExtension::KEY, CartExtension::class);
        static::assertInstanceOf(CartExtension::class, $extension);
        static::assertFalse($extension->hasCode('CODE123'));
    }

    public function testOnLineItemRemovedPromotionWithCodeNoDiscountId(): void
    {
        $cart = Generator::createCart();
        $lineItem = new LineItem('id', PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem->setReferencedId('CODE123');
        $lineItem->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_FIXED_UNIT);
        $lineItem->setRemovable(true);
        $cart->add($lineItem);
        $cart->addExtension(CartExtension::KEY, new CartExtension());

        $context = Generator::generateSalesChannelContext();
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())->method('dispatch');

        $subscriber = $this->createSubscriber($eventDispatcher);
        $event = new BeforeLineItemRemovedEvent($lineItem, $cart, $context);
        $subscriber->onLineItemRemoved($event);

        $extension = $cart->getExtensionOfType(CartExtension::KEY, CartExtension::class);
        static::assertInstanceOf(CartExtension::class, $extension);
        static::assertFalse($extension->hasCode('CODE123'));
    }

    public function testOnLineItemRemovedPromotionNoCodeButPromotionId(): void
    {
        $cart = Generator::createCart();
        $lineItem = new LineItem('id', PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('promotionId', 'PROMOID');
        $lineItem->setRemovable(true);
        $cart->add($lineItem);
        $cart->addExtension(CartExtension::KEY, new CartExtension());

        $subscriber = $this->createSubscriber();
        $event = new BeforeLineItemRemovedEvent($lineItem, $cart, Generator::generateSalesChannelContext());

        $subscriber->onLineItemRemoved($event);
        $extension = $cart->getExtensionOfType(CartExtension::KEY, CartExtension::class);
        static::assertInstanceOf(CartExtension::class, $extension);
        static::assertTrue($extension->isPromotionBlocked('PROMOID'));
    }

    private function createSubscriber(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?RequestStack $requestStack = null
    ): StorefrontCartSubscriber {
        $eventDispatcher = $eventDispatcher ?? new CollectingEventDispatcher();
        $requestStack = $requestStack ?? new RequestStack();

        return new StorefrontCartSubscriber($eventDispatcher, $requestStack);
    }
}
