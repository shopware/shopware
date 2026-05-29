<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(LogoutRoute::class)]
class LogoutRouteTest extends TestCase
{
    protected SalesChannelContextPersister&MockObject $contextPersister;

    protected EventDispatcherInterface&MockObject $eventDispatcher;

    protected SystemConfigService&MockObject $systemConfig;

    protected CartService&MockObject $cartService;

    protected LogoutRoute $route;

    protected function setUp(): void
    {
        parent::setUp();
        $this->contextPersister = $this->createMock(SalesChannelContextPersister::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->systemConfig = $this->createMock(SystemConfigService::class);
        $this->cartService = $this->createMock(CartService::class);

        $this->route = new LogoutRoute(
            $this->contextPersister,
            $this->eventDispatcher,
            $this->systemConfig,
            $this->cartService,
        );
    }

    public function testCustomerLogoutEventDispatched(): void
    {
        $context = Generator::generateSalesChannelContext();

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function ($event) use ($context) {
                if ($event instanceof CustomerLogoutEvent) {
                    return $event->getSalesChannelContext()->getToken() === $context->getToken();
                }

                return true;
            }));

        $this->route->logout($context, new RequestDataBag());
    }

    public function testReturnNewContextToken(): void
    {
        $context = Generator::generateSalesChannelContext();

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function ($event) {
                if ($event instanceof CustomerLogoutEvent) {
                    $event->setSalesChannelContext(Generator::generateSalesChannelContext(token: SalesChannelContextService::getNewToken()));
                }

                return true;
            }));

        $response = $this->route->logout($context, new RequestDataBag());

        static::assertNotSame($response->getToken(), $context->getToken());
    }

    public function testShouldKeepCartCustomerWithInactiveSystemConfig(): void
    {
        $context = Generator::generateSalesChannelContext();

        $this->systemConfig->expects($this->once())
            ->method('getBool')
            ->with('core.loginRegistration.invalidateSessionOnLogOut', $context->getSalesChannelId())
            ->willReturn(false);

        $this->cartService->expects($this->never())
            ->method('deleteCart')
            ->with($context);

        $this->contextPersister->expects($this->never())
            ->method('delete')
            ->with($context->getToken());

        $this->contextPersister->expects($this->once())
            ->method('deleteToken')
            ->with($context->getToken());

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function ($event) use ($context) {
                if ($event instanceof CustomerLogoutEvent) {
                    return $event->getSalesChannelContext()->getToken() === $context->getToken();
                }

                return true;
            }));

        $this->route->logout($context, new RequestDataBag());
    }

    public function testShouldStillDeleteCartCustomerWithActiveSystemConfig(): void
    {
        $context = Generator::generateSalesChannelContext();

        $this->systemConfig->expects($this->once())
            ->method('getBool')
            ->with('core.loginRegistration.invalidateSessionOnLogOut', $context->getSalesChannelId())
            ->willReturn(true);

        $this->cartService->expects($this->once())
            ->method('deleteCart')
            ->with($context);

        $this->contextPersister->expects($this->once())
            ->method('delete')
            ->with($context->getToken());

        $this->contextPersister->expects($this->never())
            ->method('deleteToken')
            ->with($context->getToken());

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function ($event) use ($context) {
                if ($event instanceof CustomerLogoutEvent) {
                    return $event->getSalesChannelContext()->getToken() === $context->getToken();
                }

                return true;
            }));

        $this->route->logout($context, new RequestDataBag());
    }

    public function testShouldStillDeleteCartOfGuestWithInactiveSystemConfig(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->getCustomer()?->setGuest(true);

        $this->systemConfig->expects($this->once())
            ->method('getBool')
            ->with('core.loginRegistration.invalidateSessionOnLogOut', $context->getSalesChannelId())
            ->willReturn(false);

        $this->cartService->expects($this->once())
            ->method('deleteCart')
            ->with($context);

        $this->contextPersister->expects($this->once())
            ->method('delete')
            ->with($context->getToken());

        $this->contextPersister->expects($this->never())
            ->method('deleteToken')
            ->with($context->getToken());

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::callback(function ($event) use ($context) {
                if ($event instanceof CustomerLogoutEvent) {
                    return $event->getSalesChannelContext()->getToken() === $context->getToken();
                }

                return true;
            }));

        $this->route->logout($context, new RequestDataBag());
    }
}
