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
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

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
        $expiredToken = SalesChannelContextService::getNewToken();

        $this->persister->method('load')->willReturn(['expired' => true, 'token' => $expiredToken]);

        $context = Generator::generateSalesChannelContext();
        $context->setRuleIds(['rule-1', 'rule-2']);
        $context->setAreaRuleIds([RuleAreas::PRODUCT_AREA => ['rule-1'], RuleAreas::PROMOTION_AREA => ['rule-2']]);

        $this->factory->expects($this->once())
            ->method('create')
            ->with(
                static::logicalNot(static::equalTo($expiredToken)),
                TestDefaults::SALES_CHANNEL,
                static::callback(function ($arg) {
                    $this->assertSame(Defaults::LANGUAGE_SYSTEM, $arg[SalesChannelContextService::LANGUAGE_ID]);
                    $this->assertTrue($arg['expired']);

                    return true;
                }),
            )
            ->willReturn($context);

        $cart = new Cart($expiredToken);
        $cart->setRuleIds(['rule-1', 'rule-2']);
        $result = new RuleLoaderResult($cart, new RuleCollection());

        $this->cartRuleLoader
            ->expects($this->once())
            ->method('loadByToken')
            ->with($context, static::logicalNot(static::equalTo($expiredToken)))
            ->willReturn($result);

        $this->cartService
            ->expects($this->once())
            ->method('setCart')
            ->with($result->getCart());

        $request = $this->setupSessionAndRequest();

        $this->service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $expiredToken, Defaults::LANGUAGE_SYSTEM));

        $session = $request->getSession();

        static::assertSame($context->getRuleIds(), $session->get(SalesChannelContextService::RULE_IDS));
        static::assertSame($context->getAreaRuleIds(), $session->get(SalesChannelContextService::AREA_RULE_IDS));
    }

    public function testTokenNotExpired(): void
    {
        $customerId = Uuid::randomHex();
        $noneExpiringToken = SalesChannelContextService::getNewToken();

        $this->persister->method('load')->willReturn(['expired' => false, SalesChannelContextService::CUSTOMER_ID => $customerId]);

        $context = Generator::generateSalesChannelContext();

        $this->factory->expects($this->once())
            ->method('create')
            ->with(
                $noneExpiringToken,
                TestDefaults::SALES_CHANNEL,
                static::callback(function ($arg) use ($customerId) {
                    $this->assertSame(Defaults::LANGUAGE_SYSTEM, $arg[SalesChannelContextService::LANGUAGE_ID]);
                    $this->assertSame($customerId, $arg[SalesChannelContextService::CUSTOMER_ID]);
                    $this->assertFalse($arg['expired']);

                    return true;
                })
            )
            ->willReturn($context);

        $cart = new Cart(Generator::CART_TOKEN);
        $cart->setRuleIds(['rule-3', 'rule-4']);
        $result = new RuleLoaderResult($cart, new RuleCollection());

        $this->cartRuleLoader
            ->expects($this->once())
            ->method('loadByToken')
            ->with($context, Generator::CART_TOKEN)
            ->willReturn($result);

        $this->cartService
            ->expects($this->once())
            ->method('setCart')
            ->with($result->getCart());

        $this->setupSessionAndRequest();

        $this->service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $noneExpiringToken, Defaults::LANGUAGE_SYSTEM));
    }

    public function testDispatchesSalesChannelContextCreatedEvent(): void
    {
        $token = SalesChannelContextService::getNewToken();
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

        $this->setupSessionAndRequest();

        $this->service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $token));
    }

    #[DataProvider('skipCartCalculationIfAlreadyDoneAndESISubrequestProvider')]
    public function testSkipCartCalculationIfAlreadyDoneAndESISubrequest(Request $request, bool $hasCart, bool $expectCalculation): void
    {
        $customerId = Uuid::randomHex();
        $cartToken = CartService::getNewToken();
        $result = new RuleLoaderResult(new Cart($cartToken), new RuleCollection());

        $this->persister->method('load')->willReturn(['expired' => false, SalesChannelContextService::CUSTOMER_ID => $customerId]);

        $token = SalesChannelContextService::getNewToken();
        $context = Generator::generateSalesChannelContext(token: $token, cartToken: $cartToken);

        $this->factory
            ->expects($this->once())
            ->method('create')
            ->willReturn($context);

        $this->cartService
            ->expects($this->once())
            ->method('hasCart')
            ->with($cartToken)
            ->willReturn($hasCart);

        if ($expectCalculation) {
            $this->cartRuleLoader
                ->expects($this->once())
                ->method('loadByToken')
                ->with($context, $cartToken)
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

        $session = new Session(new MockArraySessionStorage());
        $session->set(SalesChannelContextService::RULE_IDS, ['rule-1', 'rule-2']);
        $session->set(SalesChannelContextService::AREA_RULE_IDS, [RuleAreas::PRODUCT_AREA => ['rule-1'], RuleAreas::PROMOTION_AREA => ['rule-2']]);

        $request->setSession($session);
        $this->requestStack->push($request);

        $context = $this->service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $token, Defaults::LANGUAGE_SYSTEM));

        static::assertSame($session->get(SalesChannelContextService::RULE_IDS), $context->getRuleIds());
        static::assertSame($session->get(SalesChannelContextService::AREA_RULE_IDS), $context->getAreaRuleIds());
    }

    public static function skipCartCalculationIfAlreadyDoneAndESISubrequestProvider(): \Generator
    {
        yield 'esi request with cart => false' => [new Request(attributes: ['_esi' => true]), true, false];
        yield 'esi request without cart => true' => [new Request(attributes: ['_esi' => true]), false, true];
        yield 'no esi request but cart => true' => [new Request(), true, true];
        yield 'no esi request and no cart => true' => [new Request(), false, true];
    }

    public function testAddStatesFromOriginalContext(): void
    {
        $token = SalesChannelContextService::getNewToken();
        $originalContext = new Context(new SystemSource());
        $originalContext->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('withPermissions')->willReturn($this->createMock(RuleLoaderResult::class));
        $context->expects($this->once())
            ->method('addState')
            ->with(Context::ELASTICSEARCH_EXPLAIN_MODE);
        $session = [
            'foo' => 'bar',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'originalContext' => $originalContext,
        ];

        $persister = $this->createMock(SalesChannelContextPersister::class);
        $persister->method('load')->willReturn($session);

        $factory = $this->createMock(SalesChannelContextFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($token, TestDefaults::SALES_CHANNEL, $session)
            ->willReturn($context);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new SalesChannelContextCreatedEvent($context, $token, $session));

        $service = new SalesChannelContextService(
            $factory,
            $this->createMock(CartRuleLoader::class),
            $persister,
            $this->createMock(CartService::class),
            $dispatcher,
            $this->requestStack,
        );

        $this->setupSessionAndRequest();

        $service->get(new SalesChannelContextServiceParameters(
            TestDefaults::SALES_CHANNEL,
            $token,
            Defaults::LANGUAGE_SYSTEM,
            null,
            null,
            $originalContext,
        ));
    }

    public function testESIRequestsCopyRulesFromSession(): void
    {
        $token = SalesChannelContextService::getNewToken();
        $ruleIds = ['rule-1', 'rule-2', 'rule-3'];

        $this->persister->method('load')->willReturn([
            'expired' => false,
            SalesChannelContextService::CUSTOMER_ID => Uuid::randomHex(),
            SalesChannelContextService::CART_TOKEN => 'cart-token',
        ]);

        $context = $this->createMock(SalesChannelContext::class);

        $this->factory
            ->expects($this->once())
            ->method('create')
            ->willReturn($context);

        $this->cartService
            ->expects($this->once())
            ->method('hasCart')
            ->with('cart-token')
            ->willReturn(true);

        $context
            ->expects($this->once())
            ->method('setRuleIds')
            ->with($ruleIds);
        $context
            ->expects($this->once())
            ->method('getCartToken')
            ->willReturn('cart-token');

        $this->cartRuleLoader
            ->expects($this->never())
            ->method('loadByToken');
        $this->cartService
            ->expects($this->never())
            ->method('setCart');

        $this->setupSessionAndRequest([
            'sw-rule-ids' => $ruleIds,
        ], [
            '_esi' => true,
        ]);

        $this->service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $token, Defaults::LANGUAGE_SYSTEM));
    }

    /**
     * @param array<string, mixed> $sessionData
     * @param array<string, mixed> $requestAttributes
     */
    private function setupSessionAndRequest(array $sessionData = [], array $requestAttributes = []): Request
    {
        $session = new Session(new MockArraySessionStorage());

        foreach ($sessionData as $key => $value) {
            $session->set($key, $value);
        }

        $request = new Request(attributes: $requestAttributes);
        $request->setSession($session);
        $this->requestStack->push($request);

        return $request;
    }
}
