<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\RuleLoaderResult;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelContextService::class)]
class SalesChannelContextServiceTest extends TestCase
{
    private SalesChannelContextFactory&MockObject $factory;

    private CartRuleLoader&MockObject $cartRuleLoader;

    private SalesChannelContextPersister&MockObject $persister;

    private CartService&MockObject $cartService;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private RequestStack $requestStack;

    private SalesChannelContextService $service;

    protected function setUp(): void
    {
        $this->factory = $this->createMock(SalesChannelContextFactory::class);
        $this->cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $this->persister = $this->createMock(SalesChannelContextPersister::class);
        $this->cartService = $this->createMock(CartService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->requestStack = new RequestStack();

        $this->service = new SalesChannelContextService(
            $this->factory,
            $this->cartRuleLoader,
            $this->persister,
            $this->cartService,
            $this->eventDispatcher,
            $this->requestStack,
        );
    }

    public function testTokenExpired(): void
    {
        $this->persister->method('load')->willReturn(['expired' => true]);

        $expiredToken = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        $this->factory->expects($this->once())
            ->method('create')
            ->with(
                static::logicalNot(static::equalTo($expiredToken)),
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                    'expired' => true,
                ]
            )
            ->willReturn($context);

        $result = new RuleLoaderResult(new Cart($expiredToken), new RuleCollection());

        $this->cartRuleLoader
            ->expects($this->once())
            ->method('loadByToken')
            ->with($context, static::logicalNot(static::equalTo($expiredToken)))
            ->willReturn($result);

        $this->cartService
            ->expects($this->once())
            ->method('setCart')
            ->with($result->getCart());

        $this->service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $expiredToken, Defaults::LANGUAGE_SYSTEM));
    }

    public function testTokenNotExpired(): void
    {
        $customerId = Uuid::randomHex();
        $noneExpiringToken = Uuid::randomHex();

        $this->persister->method('load')->willReturn(['expired' => false, SalesChannelContextService::CUSTOMER_ID => $customerId]);

        $context = Generator::generateSalesChannelContext();

        $this->factory->expects($this->once())
            ->method('create')
            ->with(
                $noneExpiringToken,
                TestDefaults::SALES_CHANNEL,
                [
                    SalesChannelContextService::LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
                    SalesChannelContextService::CUSTOMER_ID => $customerId,
                    'expired' => false,
                ]
            )
            ->willReturn($context);

        $result = new RuleLoaderResult(new Cart($noneExpiringToken), new RuleCollection());

        $this->cartRuleLoader
            ->expects($this->once())
            ->method('loadByToken')
            ->with($context, $noneExpiringToken)
            ->willReturn($result);

        $this->cartService
            ->expects($this->once())
            ->method('setCart')
            ->with($result->getCart());

        $this->service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $noneExpiringToken, Defaults::LANGUAGE_SYSTEM));
    }

    public function testDispatchesSalesChannelContextCreatedEvent(): void
    {
        $token = 'test-token';
        $context = Generator::generateSalesChannelContext();
        $session = ['foo' => 'bar'];

        $this->persister->method('load')->willReturn($session);

        $this->factory->expects($this->once())
            ->method('create')
            ->with($token, TestDefaults::SALES_CHANNEL, $session)
            ->willReturn($context);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new SalesChannelContextCreatedEvent($context, $token, $session));

        $this->service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $token));
    }

    #[DataProvider('skipCartCalculationIfAlreadyDoneAndESISubrequestProvider')]
    public function testSkipCartCalculationIfAlreadyDoneAndESISubrequest(Request $request, bool $hasCart, bool $expectCalculation): void
    {
        $customerId = Uuid::randomHex();
        $token = Uuid::randomHex();
        $result = new RuleLoaderResult(new Cart($token), new RuleCollection());

        $this->persister->method('load')->willReturn(['expired' => false, SalesChannelContextService::CUSTOMER_ID => $customerId]);

        $context = Generator::generateSalesChannelContext();

        $this->factory
            ->expects($this->once())
            ->method('create')
            ->willReturn($context);

        $this->cartService
            ->expects($this->once())
            ->method('hasCart')
            ->with($token)
            ->willReturn($hasCart);

        if ($expectCalculation) {
            $this->cartRuleLoader
                ->expects($this->once())
                ->method('loadByToken')
                ->with($context, $token)
                ->willReturn($result);

            $this->cartService
                ->expects($this->once())
                ->method('setCart')
                ->with($result->getCart());
        } else {
            $this->cartRuleLoader
                ->expects($this->never())
                ->method(static::anything());

            $this->cartService
                ->expects($this->never())
                ->method('setCart');
        }

        $this->requestStack->push($request);

        $this->service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $token, Defaults::LANGUAGE_SYSTEM));
    }

    public static function skipCartCalculationIfAlreadyDoneAndESISubrequestProvider(): \Generator
    {
        yield 'esi request with cart => false' => [new Request(attributes: ['_sw_esi' => true]), true, false];
        yield 'esi request without cart => true' => [new Request(attributes: ['_sw_esi' => true]), false, true];
        yield 'no esi request but cart => true' => [new Request(), true, true];
        yield 'no esi request and no cart => true' => [new Request(), false, true];
    }
}
