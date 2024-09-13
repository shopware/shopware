<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartContextHashStruct;
use Shopware\Core\Checkout\Cart\Event\CartContextHashEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(CartContextHashEvent::class)]
#[Package('checkout')]
class CartContextHashEventTest extends TestCase
{
    public SalesChannelContext $salesChannelContext;

    public Cart $cart;

    public CartContextHashEvent $event;

    public CartContextHashStruct $hashStruct;

    protected function setUp(): void
    {
        $this->salesChannelContext = Generator::createSalesChannelContext();

        $this->cart = new Cart('token');

        $this->hashStruct = new CartContextHashStruct();
        $this->hashStruct->setPrice(14.0);
        $this->hashStruct->setShippingMethod('id');
        $this->hashStruct->setPaymentMethod('id');
        $this->hashStruct->addLineItem('a', ['quantity' => 1]);
        $this->hashStruct->addLineItem('b', ['quantity' => 2]);

        $this->event = new CartContextHashEvent(
            $this->salesChannelContext,
            $this->cart,
            $this->hashStruct
        );
    }

    public function testReturnsCorrectProperties(): void
    {
        static::assertSame($this->salesChannelContext, $this->event->getSalesChannelContext());
        static::assertSame($this->salesChannelContext->getContext(), $this->event->getContext());
        static::assertSame($this->cart, $this->event->getCart());
        static::assertSame($this->hashStruct, $this->event->getHashStruct());
    }

    public function testSetValues(): void
    {
        $newStruct = new CartContextHashStruct();
        $newStruct->addArrayExtension('test', ['test' => 'test value']);

        $this->event->setHashStruct($newStruct);

        $expectedStruct = new CartContextHashStruct();
        $expectedStruct->addArrayExtension('test', ['test' => 'test value']);

        static::assertEquals($expectedStruct, $this->event->getHashStruct());
    }

    public function testAddValue(): void
    {
        $struct = $this->event->getHashStruct();
        $struct->addArrayExtension('test', ['test' => 'test value']);

        $this->event->setHashStruct($struct);

        $this->hashStruct->addArrayExtension('test', ['test' => 'test value']);

        static::assertSame($this->hashStruct, $this->event->getHashStruct());
    }

    public function testRemoveValue(): void
    {
        $actualStruct = $this->event->getHashStruct();
        $actualStruct->setPrice(null);

        $this->event->setHashStruct($actualStruct);

        $expectedStruct = new CartContextHashStruct();
        $expectedStruct->setPrice(null);
        $expectedStruct->setShippingMethod('id');
        $expectedStruct->setPaymentMethod('id');
        $expectedStruct->setLineItems(
            [
                'a' => [
                    'quantity' => 1,
                ],
                'b' => [
                    'quantity' => 2,
                ],
            ]
        );

        static::assertEquals($expectedStruct, $this->event->getHashStruct());
    }
}
