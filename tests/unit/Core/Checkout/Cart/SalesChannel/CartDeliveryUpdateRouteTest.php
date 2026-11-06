<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartLocker;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\SelectedDeliveryShippingMethods;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Event\CartChangedEvent;
use Shopware\Core\Checkout\Cart\Extension\CartDeliveryUpdateRouteExtension;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartDeliveryUpdateRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartResponse;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartDeliveryUpdateRoute::class)]
class CartDeliveryUpdateRouteTest extends TestCase
{
    private ?Cart $calculatedCart = null;

    private ?Cart $persistedCart = null;

    private EventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
    }

    public function testSelectionIsStoredOnTheCartBeforeItIsRecalculated(): void
    {
        $cart = $this->createCart('parcel', 'freight');

        $changedEvents = [];
        $this->eventDispatcher->addListener(CartChangedEvent::class, static function (CartChangedEvent $event) use (&$changedEvents): void {
            $changedEvents[] = $event;
        });

        $response = $this->createRoute()->update(
            new Request([], ['deliveries' => [['key' => 'freight', 'shippingMethodId' => 'express']]]),
            $cart,
            Generator::generateSalesChannelContext(),
        );

        static::assertSame(['freight' => 'express'], $this->getSelection($this->calculatedCart));
        static::assertSame($this->calculatedCart, $this->persistedCart);
        static::assertSame($this->calculatedCart, $response->getCart());
        static::assertCount(1, $changedEvents);
    }

    public function testNullDropsTheSelectionOfTheDelivery(): void
    {
        $cart = $this->createCart('parcel', 'freight');
        $cart->addExtension(
            SelectedDeliveryShippingMethods::KEY,
            new SelectedDeliveryShippingMethods(['parcel' => 'pickup', 'freight' => 'express'])
        );

        $this->createRoute()->update(
            new Request([], ['deliveries' => [['key' => 'parcel', 'shippingMethodId' => null]]]),
            $cart,
            Generator::generateSalesChannelContext(),
        );

        static::assertSame(['freight' => 'express'], $this->getSelection($this->calculatedCart));
    }

    public function testUnknownDeliveryKeyIsRejectedWithoutChangingTheSelection(): void
    {
        $cart = $this->createCart('parcel');
        $cart->addExtension(SelectedDeliveryShippingMethods::KEY, new SelectedDeliveryShippingMethods(['parcel' => 'pickup']));

        try {
            $this->createRoute()->update(
                new Request([], ['deliveries' => [
                    ['key' => 'parcel', 'shippingMethodId' => 'express'],
                    ['key' => 'freight', 'shippingMethodId' => 'express'],
                ]]),
                $cart,
                Generator::generateSalesChannelContext(),
            );

            static::fail('The unknown delivery key was accepted');
        } catch (CartException $exception) {
            static::assertEquals(CartException::deliveryNotFound('freight'), $exception);
        }

        static::assertSame(['parcel' => 'pickup'], $this->getSelection($cart));
        static::assertNull($this->calculatedCart);
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $cart = new Cart('token');
        $context = Generator::generateSalesChannelContext();
        $response = new CartResponse(new Cart('token'));

        $this->eventDispatcher->addListener(
            CartDeliveryUpdateRouteExtension::NAME . '.pre',
            static function (CartDeliveryUpdateRouteExtension $extension) use ($request, $cart, $context, $response): void {
                static::assertSame(['request' => $request, 'cart' => $cart, 'context' => $context], $extension->getParams());

                $extension->result = $response;
                $extension->stopPropagation();
            }
        );

        static::assertSame($response, $this->createRoute()->update($request, $cart, $context));
    }

    private function createRoute(): CartDeliveryUpdateRoute
    {
        $calculator = static::createStub(CartCalculator::class);
        $calculator->method('calculate')->willReturnCallback(function (Cart $cart): Cart {
            return $this->calculatedCart = $cart;
        });

        $persister = static::createStub(AbstractCartPersister::class);
        $persister->method('save')->willReturnCallback(function (Cart $cart): void {
            $this->persistedCart = $cart;
        });

        $cartLocker = static::createStub(CartLocker::class);
        $cartLocker->method('locked')->willReturnCallback(static fn (SalesChannelContext $context, \Closure $closure) => $closure());

        return new CartDeliveryUpdateRoute(
            $persister,
            $calculator,
            $this->eventDispatcher,
            $cartLocker,
            static::createStub(DataValidator::class),
            new ExtensionDispatcher($this->eventDispatcher),
        );
    }

    private function createCart(string ...$deliveryKeys): Cart
    {
        $deliveries = new DeliveryCollection();
        foreach ($deliveryKeys as $deliveryKey) {
            $delivery = new Delivery(
                new DeliveryPositionCollection(),
                new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
                new ShippingMethodEntity(),
                new ShippingLocation(new CountryEntity(), null, null),
                new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            );
            $delivery->setPayloadValue(Delivery::DELIVERY_KEY, $deliveryKey);
            $deliveries->add($delivery);
        }

        $cart = new Cart('token');
        $cart->setDeliveries($deliveries);

        return $cart;
    }

    /**
     * @return array<array-key, string>|null
     */
    private function getSelection(?Cart $cart): ?array
    {
        return $cart?->getExtensionOfType(SelectedDeliveryShippingMethods::KEY, SelectedDeliveryShippingMethods::class)?->getShippingMethodIds();
    }
}
