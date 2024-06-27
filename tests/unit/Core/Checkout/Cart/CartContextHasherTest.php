<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartContextHasher;
use Shopware\Core\Checkout\Cart\CartContextHashStruct;
use Shopware\Core\Checkout\Cart\Event\CartContextHashEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(CartContextHasher::class)]
#[Package('checkout')]
class CartContextHasherTest extends TestCase
{
    public const EXPECTED_HASH = '4b23999825d79f8ef836181beb8c95beb298618d8edc35e7fea35b638cd533ac';

    public EventDispatcher&MockObject $eventDispatcherMock;

    public CartPrice $cartPrice;

    public Cart $cart;

    public SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->cartPrice = new CartPrice(
            11.24,
            14,
            1,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        );

        $this->cart = new Cart('token');
        $this->cart->setPrice($this->cartPrice);

        $lineItemChild = new LineItem('line-item-child-id', 'product', 'referenceId2');
        $lineItemChild->setPrice(new CalculatedPrice(
            1,
            24,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));

        $lineItem1 = new LineItem('line-item-parent-id', 'product', 'referenceId');
        $lineItem1->setPrice(new CalculatedPrice(
            1,
            12,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));
        $lineItem1->addChild($lineItemChild);

        $lineItem2 = new LineItem('line-item-id', 'product', 'referenceId3', 2);
        $lineItem2->setPrice(new CalculatedPrice(
            1,
            14,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));

        $this->cart->add($lineItem1);
        $this->cart->add($lineItem2);

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('id');

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('id');

        $this->context = Generator::createSalesChannelContext(
            paymentMethod: $paymentMethod,
            shippingMethod: $shippingMethod
        );
    }

    public function testHashIsValid(): void
    {
        $eventDispatcher = new EventDispatcher();

        $cartContextHashService = new CartContextHasher($eventDispatcher);

        $result = $cartContextHashService->isMatching(self::EXPECTED_HASH, $this->cart, $this->context);

        static::assertTrue($result);
    }

    public function testHashIsNotValid(): void
    {
        $eventDispatcher = new EventDispatcher();

        $cartContextHashService = new CartContextHasher($eventDispatcher);

        $result = $cartContextHashService->isMatching('d1942d08767c950d9398bf651fafbb99c580e4e055a9978098be4045b5b93f97', $this->cart, $this->context);

        static::assertFalse($result);
    }

    public function testGetHash(): void
    {
        $eventDispatcher = new EventDispatcher();

        $cartContextHashService = new CartContextHasher($eventDispatcher);

        $result = $cartContextHashService->generate($this->cart, $this->context);

        static::assertSame(self::EXPECTED_HASH, $result);
    }

    public function testCartContextHashChangeEventDispatch(): void
    {
        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);

        $hashStruct = new CartContextHashStruct();
        $hashStruct->setPrice(14.0);
        $hashStruct->setPaymentMethod('id');
        $hashStruct->setLineItems([
            'line-item-parent-id' => [
                'quantity' => 1,
                'price' => 12.0,
                'referenceId' => 'referenceId',
                'children' => [
                    'line-item-child-id' => [
                        'quantity' => 1,
                        'price' => 24.0,
                        'referenceId' => 'referenceId2',
                        'children' => [],
                    ],
                ],
            ],
            'line-item-id' => [
                'quantity' => 2,
                'price' => 14.0,
                'referenceId' => 'referenceId3',
                'children' => [],
            ],
        ]);
        $hashStruct->setShippingMethod('id');

        $this->eventDispatcherMock->expects(static::once())->method('dispatch')
                ->with($event = new CartContextHashEvent($this->context, $this->cart, $hashStruct))
                ->willReturn($event);

        $cartContextHashService = new CartContextHasher($this->eventDispatcherMock);

        $cartContextHashService->generate($this->cart, $this->context);
    }
}
