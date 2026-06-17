<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
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
#[Package('discovery')]
#[CoversClass(CartRestorer::class)]
class CartRestorerTest extends TestCase
{
    private MockObject&SalesChannelContextFactory $salesChannelContextFactory;

    private SalesChannelContextPersister&MockObject $persister;

    private CartService&MockObject $cartService;

    private CartRuleLoader&MockObject $cartRuleLoader;

    private CartPersister&MockObject $cartPersister;

    private EventDispatcher $eventDispatcher;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->salesChannelContextFactory = $this->createMock(SalesChannelContextFactory::class);
        $this->persister = $this->createMock(SalesChannelContextPersister::class);
        $this->cartService = $this->createMock(CartService::class);
        $this->cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $this->cartPersister = $this->createMock(CartPersister::class);
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

        $this->cartRuleLoader->expects($this->once())
            ->method('loadByToken')
            ->with($customerContext, $token);

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
            $this->cartRuleLoader,
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
            $this->cartRuleLoader,
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

        $this->cartRuleLoader->expects($this->once())
            ->method('loadByToken')
            ->with($customerContext, $newToken);

        $cartRestorer = new CartRestorer(
            $this->salesChannelContextFactory,
            $this->persister,
            $this->cartService,
            $this->cartRuleLoader,
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
        $this->cartRuleLoader->expects($this->never())->method('loadByToken');

        $cartRestorer = new CartRestorer(
            $this->salesChannelContextFactory,
            $this->persister,
            $this->cartService,
            $this->cartRuleLoader,
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
            $this->cartRuleLoader,
            $this->cartPersister,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restoreByToken($token, 'myCustomer', $salesChannelContext);
        static::assertSame($token, $result->getToken());
        static::assertTrue($eventIsThrown);
    }
}
