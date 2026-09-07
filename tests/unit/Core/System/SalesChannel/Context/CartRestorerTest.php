<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\PriceModifier\PriceModifierIdExtension;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CartRestorer::class)]
class CartRestorerTest extends TestCase
{
    private MockObject&SalesChannelContextFactory $salesChannelContextFactory;

    private SalesChannelContextPersister&MockObject $persister;

    private CartService&Stub $cartService;

    private CartPersister&Stub $cartPersister;

    private EventDispatcher $eventDispatcher;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->salesChannelContextFactory = $this->createMock(SalesChannelContextFactory::class);
        $this->persister = $this->createMock(SalesChannelContextPersister::class);
        $this->cartService = static::createStub(CartService::class);
        $this->cartPersister = static::createStub(CartPersister::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->requestStack = new RequestStack();
    }

    public function testRestoreByTokenWithoutExistingToken(): void
    {
        $token = 'myToken';
        $salesChannelContext = Generator::generateSalesChannelContext();
        $this->persister->expects($this->once())->method('load')->with($token, $salesChannelContext->getSalesChannelId())->willReturn([]);
        $this->persister->expects($this->once())->method('save');

        $customerContext = Generator::generateSalesChannelContext(token: $token);
        $this->salesChannelContextFactory->expects($this->once())
            ->method('create')
            ->with($token, $salesChannelContext->getSalesChannelId(), [
                SalesChannelContextService::CUSTOMER_ID => 'myCustomer',
                SalesChannelContextService::LANGUAGE_ID => $salesChannelContext->getLanguageId(),
                SalesChannelContextService::CURRENCY_ID => $salesChannelContext->getCurrencyId(),
                SalesChannelContextService::DOMAIN_ID => $salesChannelContext->getDomainId(),
            ])
            ->willReturn($customerContext);

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator->expects($this->once())
            ->method('calculateByToken')
            ->with($token, $customerContext);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            SalesChannelContextRestoredEvent::class,
            static function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        $cartRestorer = new CartRestorer(
            $this->salesChannelContextFactory,
            $this->persister,
            $this->cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restoreByToken($token, 'myCustomer', $salesChannelContext);
        static::assertSame($customerContext, $result);
        static::assertSame($token, $result->getToken());
        static::assertFalse($eventIsThrown);
    }

    public function testRestoreByToken(): void
    {
        $token = 'myToken';
        $salesChannelContext = Generator::generateSalesChannelContext();
        $this->persister->expects($this->once())->method('load')->with($token, $salesChannelContext->getSalesChannelId())->willReturn([
            'token' => $token,
            'expired' => false,
        ]);
        $this->persister->expects($this->never())->method('save');

        $this->salesChannelContextFactory->expects($this->once())->method('create')->willReturn(
            Generator::generateSalesChannelContext(token: $token)
        );

        $cartCalculator = static::createStub(CartCalculator::class);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            SalesChannelContextRestoredEvent::class,
            static function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        $cartRestorer = new CartRestorer(
            $this->salesChannelContextFactory,
            $this->persister,
            $this->cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restoreByToken($token, 'myCustomer', $salesChannelContext);
        static::assertSame($token, $result->getToken());
        static::assertTrue($eventIsThrown);
    }

    public function testRestoreWithoutExistingCustomerContextCreatesCustomerContext(): void
    {
        $customerId = 'myCustomer';
        $newToken = 'newToken';
        $currentContext = Generator::generateSalesChannelContext();
        $currentContext->addState('foo');

        // No persisted customer context exists, e.g. because all customer tokens
        // were revoked after a password change.
        $this->persister->expects($this->once())
            ->method('load')
            ->with($currentContext->getToken(), $currentContext->getSalesChannelId(), $customerId)
            ->willReturn([
                'token' => $currentContext->getToken(),
                'expired' => false,
            ]);
        $this->persister->expects($this->once())->method('replace')->willReturn($newToken);
        $this->persister->expects($this->once())->method('save');

        $customerContext = Generator::generateSalesChannelContext(token: $newToken);
        $this->salesChannelContextFactory->expects($this->once())
            ->method('create')
            ->with($newToken, $currentContext->getSalesChannelId(), [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
                SalesChannelContextService::LANGUAGE_ID => $currentContext->getLanguageId(),
                SalesChannelContextService::CURRENCY_ID => $currentContext->getCurrencyId(),
                SalesChannelContextService::DOMAIN_ID => $currentContext->getDomainId(),
            ])
            ->willReturn($customerContext);

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator->expects($this->once())
            ->method('calculateByToken')
            ->with($newToken, $customerContext);

        $cartRestorer = new CartRestorer(
            $this->salesChannelContextFactory,
            $this->persister,
            $this->cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restore($customerId, $currentContext);

        static::assertSame($customerContext, $result);
        static::assertTrue($result->hasState('foo'));
    }

    public function testRestoreWithSameCustomerInContextKeepsContext(): void
    {
        $currentContext = Generator::generateSalesChannelContext();
        $customer = $currentContext->getCustomer();
        static::assertNotNull($customer);

        $this->persister->expects($this->once())
            ->method('load')
            ->willReturn([
                'token' => $currentContext->getToken(),
                'expired' => false,
            ]);
        $this->persister->expects($this->once())->method('replace')->willReturn('newToken');
        $this->persister->expects($this->once())->method('save');

        $this->salesChannelContextFactory->expects($this->never())->method('create');
        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator->expects($this->never())->method('calculateByToken');

        $cartRestorer = new CartRestorer(
            $this->salesChannelContextFactory,
            $this->persister,
            $this->cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restore($customer->getId(), $currentContext);

        static::assertSame($currentContext, $result);
        static::assertSame('newToken', $result->getToken());
    }

    public function testRestoreByTokenWithExpiredToken(): void
    {
        $token = 'myToken';
        $salesChannelContext = Generator::generateSalesChannelContext();
        $this->persister->expects($this->once())->method('load')->with($token, $salesChannelContext->getSalesChannelId())->willReturn([
            'token' => $token,
            'expired' => true,
        ]);
        $this->persister->expects($this->once())->method('save');

        // The first call creates the context from the expired payload, the second one creates
        // the customer context, as the expired payload does not contain the customer anymore.
        $this->salesChannelContextFactory->expects($this->exactly(2))->method('create')->willReturnOnConsecutiveCalls(
            Generator::generateSalesChannelContext(token: $token),
            Generator::generateSalesChannelContext(token: $token)
        );

        $cartCalculator = static::createStub(CartCalculator::class);

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            SalesChannelContextRestoredEvent::class,
            static function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        $cartRestorer = new CartRestorer(
            $this->salesChannelContextFactory,
            $this->persister,
            $this->cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restoreByToken($token, 'myCustomer', $salesChannelContext);
        static::assertSame($token, $result->getToken());
        static::assertTrue($eventIsThrown);
    }

    public function testRestoreMergesGuestPriceModifierIdsIntoExistingCustomerCartWithoutDuplicates(): void
    {
        $guestToken = 'guestToken';
        $customerToken = 'customerToken';

        $guestModifierIds = new PriceModifierIdExtension();
        $guestModifierIds->add('guest-only-modifier');
        $guestModifierIds->add('shared-modifier');
        $guestCart = new Cart($guestToken);
        $guestCart->addExtension(PriceModifierIdExtension::KEY, $guestModifierIds);

        $customerModifierIds = new PriceModifierIdExtension();
        $customerModifierIds->add('customer-only-modifier');
        $customerModifierIds->add('shared-modifier');
        $customerCart = new Cart($customerToken);
        $customerCart->addExtension(PriceModifierIdExtension::KEY, $customerModifierIds);

        $result = $this->restoreAndCaptureFinalCart($guestToken, $guestCart, $customerToken, $customerCart);

        $mergedModifierIds = $result->getExtension(PriceModifierIdExtension::KEY);
        static::assertInstanceOf(PriceModifierIdExtension::class, $mergedModifierIds);
        // Customer's own ids are kept, the guest's new id is appended, and the id both carts
        // already shared is not duplicated.
        static::assertSame(['customer-only-modifier', 'shared-modifier', 'guest-only-modifier'], $mergedModifierIds->getIds());
    }

    public function testRestoreCopiesGuestPriceModifierIdsWhenCustomerCartHasNoneYet(): void
    {
        $guestToken = 'guestToken';
        $customerToken = 'customerToken';

        $guestModifierIds = new PriceModifierIdExtension();
        $guestModifierIds->add('guest-modifier');
        $guestCart = new Cart($guestToken);
        $guestCart->addExtension(PriceModifierIdExtension::KEY, $guestModifierIds);

        // The customer cart has no PriceModifierIdExtension of its own yet.
        $customerCart = new Cart($customerToken);

        $result = $this->restoreAndCaptureFinalCart($guestToken, $guestCart, $customerToken, $customerCart);

        $mergedModifierIds = $result->getExtension(PriceModifierIdExtension::KEY);
        static::assertInstanceOf(PriceModifierIdExtension::class, $mergedModifierIds);
        static::assertSame(['guest-modifier'], $mergedModifierIds->getIds());
    }

    /**
     * Drives CartRestorer::restoreByToken() far enough to reach enrichCustomerContext()'s
     * PriceModifierIdExtension merge, and returns the exact Cart instance it hands to
     * CartService::setCart() -- the final, persisted state of the restored cart.
     */
    private function restoreAndCaptureFinalCart(string $guestToken, Cart $guestCart, string $customerToken, Cart $customerCart): Cart
    {
        $currentContext = Generator::generateSalesChannelContext(token: $guestToken);
        $customerContext = Generator::generateSalesChannelContext(token: $customerToken);

        $this->persister->expects($this->once())->method('load')->willReturn([
            'token' => $customerToken,
            'expired' => false,
        ]);

        $this->salesChannelContextFactory->expects($this->once())->method('create')->willReturn($customerContext);

        $cartService = $this->createMock(CartService::class);
        $cartService->method('getCart')->willReturnCallback(
            static fn (string $token): Cart => match ($token) {
                $guestToken => $guestCart,
                $customerToken => $customerCart,
                default => throw new \LogicException('Unexpected cart token: ' . $token),
            }
        );
        // Guest cart has no line items in these tests, so enrichCustomerContext() takes the
        // recalculate() branch, not mergeCart() -- recalculate() is a no-op here, since only the
        // extension merge (which already happened directly on $customerCart) is under test.
        $cartService->method('recalculate')->willReturnArgument(0);

        $capturedCart = null;
        $cartService->expects($this->once())
            ->method('setCart')
            ->willReturnCallback(function (Cart $cart) use (&$capturedCart): void {
                $capturedCart = $cart;
            });

        // enrichCustomerContext()'s final step re-derives the cart it hands to setCart() from
        // this call rather than reusing $restoredCart directly -- extensions survive because the
        // real implementation reloads the same persisted cart, extensions included.
        $cartCalculator = static::createStub(CartCalculator::class);
        $cartCalculator->method('calculateByToken')->willReturn($customerCart);

        $cartRestorer = new CartRestorer(
            $this->salesChannelContextFactory,
            $this->persister,
            $cartService,
            $cartCalculator,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $cartRestorer->restoreByToken('lookupToken', 'myCustomer', $currentContext);

        static::assertInstanceOf(Cart::class, $capturedCart);

        return $capturedCart;
    }
}
