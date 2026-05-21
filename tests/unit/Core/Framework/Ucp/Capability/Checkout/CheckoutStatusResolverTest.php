<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Checkout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutStatus;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutStatusResolver;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(CheckoutStatusResolver::class)]
class CheckoutStatusResolverTest extends TestCase
{
    public function testEmptyCartIsIncomplete(): void
    {
        $cart = $this->mockCart([], []);
        $sc = $this->mockContext(true, true);

        $resolver = new CheckoutStatusResolver();
        static::assertSame(CheckoutStatus::INCOMPLETE, $resolver->resolve($cart, $sc));
    }

    public function testOrderJustPlacedReturnsCompleteInProgress(): void
    {
        $cart = $this->mockCart(['l1'], []);
        $sc = $this->mockContext(true, true);

        $resolver = new CheckoutStatusResolver();
        static::assertSame(CheckoutStatus::COMPLETE_IN_PROGRESS, $resolver->resolve($cart, $sc, orderJustPlaced: true));
    }

    public function testUnrecoverableErrorEscalates(): void
    {
        $error = $this->mockError(level: 20, persistent: true);
        $cart = $this->mockCart(['l1'], [$error]);
        $sc = $this->mockContext(true, true);

        $resolver = new CheckoutStatusResolver();
        static::assertSame(CheckoutStatus::REQUIRES_ESCALATION, $resolver->resolve($cart, $sc));
    }

    public function testBuyerInputErrorEscalates(): void
    {
        $error = $this->mockError(level: 10, persistent: true);
        $cart = $this->mockCart(['l1'], [$error]);
        $sc = $this->mockContext(true, true);

        $resolver = new CheckoutStatusResolver();
        static::assertSame(CheckoutStatus::REQUIRES_ESCALATION, $resolver->resolve($cart, $sc));
    }

    public function testMissingShippingAddressIsIncomplete(): void
    {
        $cart = $this->mockCart(['l1'], []);
        $sc = $this->mockContext(false, true);

        $resolver = new CheckoutStatusResolver();
        static::assertSame(CheckoutStatus::INCOMPLETE, $resolver->resolve($cart, $sc));
    }

    public function testReadyForCompleteWhenAllRequirementsMet(): void
    {
        $cart = $this->mockCart(['l1'], []);
        $sc = $this->mockContext(true, true);

        $resolver = new CheckoutStatusResolver();
        static::assertSame(CheckoutStatus::READY_FOR_COMPLETE, $resolver->resolve($cart, $sc));
    }

    /**
     * @param list<string> $lineItemIds
     * @param list<Error> $errors
     */
    private function mockCart(array $lineItemIds, array $errors): Cart
    {
        $cart = static::createStub(Cart::class);
        $items = new LineItemCollection();
        // We rely on `count()` from the LineItemCollection — using its real implementation is fine.
        // Add stub `LineItem` instances if non-empty:
        foreach ($lineItemIds as $id) {
            $li = new LineItem($id, 'product', $id, 1);
            $items->add($li);
        }
        $cart->method('getLineItems')->willReturn($items);

        $errorCollection = new ErrorCollection();
        foreach ($errors as $e) {
            $errorCollection->add($e);
        }
        $cart->method('getErrors')->willReturn($errorCollection);

        return $cart;
    }

    private function mockError(int $level, bool $persistent): Error
    {
        // `\Exception::getMessage()` is final — subclasses can only set the
        // message via the parent constructor, not override the method. The
        // anonymous subclass here therefore passes the desired message into
        // `Error::__construct` (which forwards to `Exception::__construct`).
        $err = new class($level, $persistent) extends Error {
            public function __construct(private readonly int $lvl, private readonly bool $persist)
            {
                parent::__construct('test');
            }

            public function isPersistent(): bool
            {
                return $this->persist;
            }

            public function getLevel(): int
            {
                return $this->lvl;
            }

            public function blockOrder(): bool
            {
                return $this->persist;
            }

            public function getKey(): string
            {
                return 'test_error_' . $this->lvl;
            }

            public function getId(): string
            {
                return 'test_error_' . $this->lvl;
            }

            public function getMessageKey(): string
            {
                return 'test_error_' . $this->lvl;
            }

            public function getParameters(): array
            {
                return [];
            }
        };

        return $err;
    }

    private function mockContext(bool $hasAddress, bool $hasPayment): SalesChannelContext
    {
        $sc = static::createStub(SalesChannelContext::class);

        $shippingLocation = static::createStub(ShippingLocation::class);
        $address = $hasAddress ? static::createStub(CustomerAddressEntity::class) : null;
        $shippingLocation->method('getAddress')->willReturn($address);

        $sc->method('getCustomer')->willReturn(null);
        $sc->method('getShippingLocation')->willReturn($shippingLocation);

        $pm = static::createStub(PaymentMethodEntity::class);
        $pm->method('getId')->willReturn($hasPayment ? 'payment-method-id' : '');
        $sc->method('getPaymentMethod')->willReturn($pm);

        return $sc;
    }
}
