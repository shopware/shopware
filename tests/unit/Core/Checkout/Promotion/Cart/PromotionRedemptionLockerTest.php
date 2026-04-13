<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Extension\CheckoutPlaceOrderExtension;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Cart\Extension\LockExtension;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Cart\PromotionRedemptionLocker;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Test\Generator;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

/**
 * @internal
 */
#[CoversClass(PromotionRedemptionLocker::class)]
class PromotionRedemptionLockerTest extends TestCase
{
    public function testSubscribeEvents(): void
    {
        $expectedEvents = [
            'checkout.place-order.pre' => 'acquireLocks',
            'checkout.place-order.error' => 'releaseLocks',
            'checkout.place-order.post' => 'releaseLocks',
        ];

        $subscribedEvents = PromotionRedemptionLocker::getSubscribedEvents();

        static::assertSame($expectedEvents, $subscribedEvents);
    }

    public function testAcquireLockWithValidPromotionItem(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(true)
            ->willReturn(true);

        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('promotion-promotion-code', 5.0, true)
            ->willReturn($lock);

        $locker = new PromotionRedemptionLocker($lockFactory);

        $cart = new Cart('test');
        $lineItem = new LineItem('id', PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('code', 'promotion-code');
        $lineItem->setPayloadValue('limitedRedemptions', true);
        $cart->add($lineItem);
        $extension = new CheckoutPlaceOrderExtension($cart, Generator::generateSalesChannelContext(), new RequestDataBag());

        $locker->acquireLocks($extension);

        $lockExtension = $extension->getExtensionOfType(LockExtension::KEY, LockExtension::class);
        static::assertNotNull($lockExtension);

        static::assertSame([$lineItem->getPayloadValue('code') => $lock], $lockExtension->locks);
    }

    public function testAcquireLockWithMultiplePromotionItem(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(true)
            ->willReturn(true);

        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('promotion-promotion-code', 5.0, true)
            ->willReturn($lock);

        $locker = new PromotionRedemptionLocker($lockFactory);

        $cart = new Cart('test');
        $firstLineItem = new LineItem('id', PromotionProcessor::LINE_ITEM_TYPE);
        $firstLineItem->setPayloadValue('code', 'promotion-code');
        $firstLineItem->setPayloadValue('limitedRedemptions', true);
        $secondLineItem = new LineItem('otherid', PromotionProcessor::LINE_ITEM_TYPE);
        $secondLineItem->setPayloadValue('code', 'promotion-code');
        $secondLineItem->setPayloadValue('limitedRedemptions', true);
        $cart->add($firstLineItem);
        $cart->add($secondLineItem);
        $extension = new CheckoutPlaceOrderExtension($cart, Generator::generateSalesChannelContext(), new RequestDataBag());

        $locker->acquireLocks($extension);

        $lockExtension = $extension->getExtensionOfType(LockExtension::KEY, LockExtension::class);
        static::assertNotNull($lockExtension);

        static::assertSame([$firstLineItem->getPayloadValue('code') => $lock], $lockExtension->locks);
    }

    public function testAcquireLockWithValidPromotionItemFails(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->expects($this->once())
            ->method('acquire')
            ->with(true)
            ->willReturn(false);

        $lockFactory->expects($this->once())
            ->method('createLock')
            ->with('promotion-promotion-code', 5.0, true)
            ->willReturn($lock);

        $locker = new PromotionRedemptionLocker($lockFactory);

        $cart = new Cart('test');
        $lineItem = new LineItem('id', PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('code', 'promotion-code');
        $lineItem->setPayloadValue('limitedRedemptions', true);
        $cart->add($lineItem);
        $extension = new CheckoutPlaceOrderExtension($cart, Generator::generateSalesChannelContext(), new RequestDataBag());

        $this->expectException(PromotionException::class);
        $this->expectExceptionMessage('Promotion promotion-code is locked due to concurrent write operation. Please try again later.');
        $locker->acquireLocks($extension);
    }

    public function testAcquireLockWithUnlimitedPromotionItem(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);

        $lockFactory->expects($this->never())
            ->method('createLock');

        $locker = new PromotionRedemptionLocker($lockFactory);

        $cart = new Cart('test');
        $lineItem = new LineItem('id', PromotionProcessor::LINE_ITEM_TYPE);
        $lineItem->setPayloadValue('code', 'promotion-code');
        $lineItem->setPayloadValue('limitedRedemptions', false);
        $cart->add($lineItem);
        $extension = new CheckoutPlaceOrderExtension($cart, Generator::generateSalesChannelContext(), new RequestDataBag());

        $locker->acquireLocks($extension);

        $lockExtension = $extension->getExtensionOfType(LockExtension::KEY, LockExtension::class);
        static::assertNull($lockExtension);
    }

    public function testAcquireLockWithoutPromotionItem(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);

        $lockFactory->expects($this->never())
            ->method('createLock');

        $locker = new PromotionRedemptionLocker($lockFactory);

        $cart = new Cart('test');
        $lineItem = new LineItem('id', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $cart->add($lineItem);
        $extension = new CheckoutPlaceOrderExtension($cart, Generator::generateSalesChannelContext(), new RequestDataBag());

        $locker->acquireLocks($extension);

        $lockExtension = $extension->getExtensionOfType(LockExtension::KEY, LockExtension::class);
        static::assertNull($lockExtension);
    }

    public function testReleaseLocks(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $locker = new PromotionRedemptionLocker($lockFactory);

        $lock1 = $this->createMock(SharedLockInterface::class);
        $lock1->expects($this->once())
            ->method('release');
        $lock2 = $this->createMock(SharedLockInterface::class);
        $lock2->expects($this->once())
            ->method('release');

        $extension = new CheckoutPlaceOrderExtension(new Cart('test'), Generator::generateSalesChannelContext(), new RequestDataBag());
        $extension->addExtension(LockExtension::KEY, new LockExtension([
            'promotion-code' => $lock1,
            'another-promotion-code' => $lock2,
        ]));

        $locker->releaseLocks($extension);
        $lockExtension = $extension->getExtensionOfType(LockExtension::KEY, LockExtension::class);
        static::assertNull($lockExtension);
    }

    public function testReleaseLocksWithoutExtension(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $locker = new PromotionRedemptionLocker($lockFactory);

        $extension = new CheckoutPlaceOrderExtension(new Cart('test'), Generator::generateSalesChannelContext(), new RequestDataBag());

        $locker->releaseLocks($extension);
        static::expectNotToPerformAssertions();
    }

    public function testGetLockKey(): void
    {
        $locker = new PromotionRedemptionLocker($this->createMock(LockFactory::class));
        $key = $locker->getLockKey('promotion-id');
        static::assertSame('promotion-promotion-id', $key);
    }
}
