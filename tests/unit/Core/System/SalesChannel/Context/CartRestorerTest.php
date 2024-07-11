<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CartRestorer::class)]
class CartRestorerTest extends TestCase
{
    private MockObject&SalesChannelContextFactory $salesChannelContextFactory;

    private SalesChannelContextPersister&MockObject $persister;

    private CartService&MockObject $cartService;

    private CartRuleLoader&MockObject $cartRuleLoader;

    private EventDispatcher $eventDispatcher;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->salesChannelContextFactory = $this->createMock(SalesChannelContextFactory::class);
        $this->persister = $this->createMock(SalesChannelContextPersister::class);
        $this->cartService = $this->createMock(CartService::class);
        $this->cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->requestStack = new RequestStack();
    }

    public function testRestoreByTokenWithoutExistingToken(): void
    {
        $token = 'myToken';
        $salesChannelContext = Generator::createSalesChannelContext();
        $this->persister->expects(static::once())->method('load')->with($token, $salesChannelContext->getSalesChannelId())->willReturn([]);
        $this->persister->expects(static::once())->method('save');

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            SalesChannelContextRestoredEvent::class,
            function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        $cartRestorer = new CartRestorer(
            $this->salesChannelContextFactory,
            $this->persister,
            $this->cartService,
            $this->cartRuleLoader,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restoreByToken($token, 'myCustomer', $salesChannelContext);
        static::assertSame($token, $result->getToken());
        static::assertFalse($eventIsThrown);
    }

    public function testRestoreByToken(): void
    {
        $token = 'myToken';
        $salesChannelContext = Generator::createSalesChannelContext();
        $this->persister->expects(static::once())->method('load')->with($token, $salesChannelContext->getSalesChannelId())->willReturn([
            'token' => $token,
            'expired' => false,
        ]);
        $this->persister->expects(static::never())->method('save');

        $this->salesChannelContextFactory->expects(static::once())->method('create')->willReturn(
            Generator::createSalesChannelContext(
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                $token,
                ''
            )
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            SalesChannelContextRestoredEvent::class,
            function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        $cartRestorer = new CartRestorer(
            $this->salesChannelContextFactory,
            $this->persister,
            $this->cartService,
            $this->cartRuleLoader,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restoreByToken($token, 'myCustomer', $salesChannelContext);
        static::assertSame($token, $result->getToken());
        static::assertTrue($eventIsThrown);
    }

    public function testRestoreByTokenWithExpiredToken(): void
    {
        $token = 'myToken';
        $salesChannelContext = Generator::createSalesChannelContext();
        $this->persister->expects(static::once())->method('load')->with($token, $salesChannelContext->getSalesChannelId())->willReturn([
            'token' => $token,
            'expired' => true,
        ]);
        $this->persister->expects(static::once())->method('save');

        $this->salesChannelContextFactory->expects(static::once())->method('create')->willReturn(
            Generator::createSalesChannelContext(
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                $token,
            )
        );

        $eventIsThrown = false;
        $this->eventDispatcher->addListener(
            SalesChannelContextRestoredEvent::class,
            function () use (&$eventIsThrown): void {
                $eventIsThrown = true;
            }
        );

        $cartRestorer = new CartRestorer(
            $this->salesChannelContextFactory,
            $this->persister,
            $this->cartService,
            $this->cartRuleLoader,
            $this->eventDispatcher,
            $this->requestStack
        );

        $result = $cartRestorer->restoreByToken($token, 'myCustomer', $salesChannelContext);
        static::assertSame($token, $result->getToken());
        static::assertTrue($eventIsThrown);
    }
}
