<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
use Shopware\Core\System\SalesChannel\SalesChannelException;
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
#[Package('framework')]
#[CoversClass(SalesChannelContextService::class)]
class SalesChannelContextServiceTest extends TestCase
{
    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->requestStack = new RequestStack();
    }

    public function testTokenExpired(): void
    {
        $persister = static::createStub(SalesChannelContextPersister::class);
        $persister->method('load')->willReturn(['expired' => true]);

        $expiredToken = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();
        $context->setRuleIds(['rule-1', 'rule-2']);
        $context->setAreaRuleIds([RuleAreas::PRODUCT_AREA => ['rule-1'], RuleAreas::PROMOTION_AREA => ['rule-2']]);

        $factory = $this->createMock(SalesChannelContextFactory::class);
        $factory->expects($this->once())
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

        $cart = new Cart($expiredToken);
        $cart->setRuleIds(['rule-1', 'rule-2']);
        $result = new RuleLoaderResult($cart, new RuleCollection());

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->once())
            ->method('loadByToken')
            ->with($context, static::logicalNot(static::equalTo($expiredToken)))
            ->willReturn($result);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('setCart')
            ->with($result->getCart());

        $request = $this->setupSessionAndRequest();

        $service = new SalesChannelContextService(
            $factory,
            $cartRuleLoader,
            $persister,
            $cartService,
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );

        $service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $expiredToken, Defaults::LANGUAGE_SYSTEM));

        $session = $request->getSession();

        static::assertSame($context->getRuleIds(), $session->get(SalesChannelContextService::RULE_IDS));
        static::assertSame($context->getAreaRuleIds(), $session->get(SalesChannelContextService::AREA_RULE_IDS));
    }

    public function testTokenNotExpired(): void
    {
        $customerId = Uuid::randomHex();
        $noneExpiringToken = Uuid::randomHex();

        $persister = static::createStub(SalesChannelContextPersister::class);
        $persister->method('load')->willReturn(['expired' => false, SalesChannelContextService::CUSTOMER_ID => $customerId]);

        $context = Generator::generateSalesChannelContext();

        $factory = $this->createMock(SalesChannelContextFactory::class);
        $factory->expects($this->once())
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

        $cart = new Cart($noneExpiringToken);
        $cart->setRuleIds(['rule-3', 'rule-4']);
        $result = new RuleLoaderResult($cart, new RuleCollection());

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->once())
            ->method('loadByToken')
            ->with($context, $noneExpiringToken)
            ->willReturn($result);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('setCart')
            ->with($result->getCart());

        $this->setupSessionAndRequest();

        $service = new SalesChannelContextService(
            $factory,
            $cartRuleLoader,
            $persister,
            $cartService,
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );

        $service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $noneExpiringToken, Defaults::LANGUAGE_SYSTEM));
    }

    #[DataProvider('stalePersistedOptionProvider')]
    public function testFallsBackWhenPersistedOptionIsNoLongerAvailable(string $option, SalesChannelException $exception, ?string $fallbackCurrencyId): void
    {
        $token = Uuid::randomHex();
        $staleId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();
        $session = [$option => $staleId];
        $call = 0;

        $persister = $this->createMock(SalesChannelContextPersister::class);
        $persister->expects($this->once())
            ->method('load')
            ->with($token, TestDefaults::SALES_CHANNEL)
            ->willReturn($session);
        $persister->expects($this->once())
            ->method('save')
            ->with($token, [$option => null], TestDefaults::SALES_CHANNEL);

        $factory = $this->createMock(SalesChannelContextFactory::class);
        $factory->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function (string $actualToken, string $salesChannelId, array $options) use ($token, $option, $staleId, $exception, $context, $fallbackCurrencyId, &$call): SalesChannelContext {
                static::assertSame($token, $actualToken);
                static::assertSame(TestDefaults::SALES_CHANNEL, $salesChannelId);

                if ($call++ === 0) {
                    static::assertSame($staleId, $options[$option]);

                    throw $exception;
                }

                if ($fallbackCurrencyId !== null) {
                    static::assertSame($fallbackCurrencyId, $options[SalesChannelContextService::CURRENCY_ID]);
                } else {
                    static::assertArrayNotHasKey($option, $options);
                }

                return $context;
            });

        $service = $this->createContextService($factory, $persister, $token);

        static::assertSame($context, $service->get(new SalesChannelContextServiceParameters(
            salesChannelId: TestDefaults::SALES_CHANNEL,
            token: $token,
            currencyId: $fallbackCurrencyId,
        )));
    }

    public static function stalePersistedOptionProvider(): \Generator
    {
        $unavailableLanguageId = Uuid::randomHex();
        yield 'language' => [
            SalesChannelContextService::LANGUAGE_ID,
            SalesChannelException::providedLanguageNotAvailable($unavailableLanguageId, [Defaults::LANGUAGE_SYSTEM]),
            null,
        ];

        yield 'currency' => [
            SalesChannelContextService::CURRENCY_ID,
            SalesChannelException::currencyNotFound(Uuid::randomHex()),
            null,
        ];

        yield 'currency uses domain fallback' => [
            SalesChannelContextService::CURRENCY_ID,
            SalesChannelException::currencyNotFound(Uuid::randomHex()),
            Uuid::randomHex(),
        ];
    }

    public function testFallsBackWhenBothPersistedOptionsAreNoLongerAvailable(): void
    {
        $token = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();
        $session = [
            SalesChannelContextService::LANGUAGE_ID => Uuid::randomHex(),
            SalesChannelContextService::CURRENCY_ID => Uuid::randomHex(),
        ];
        $persistedOptions = [];

        $persister = $this->createMock(SalesChannelContextPersister::class);
        $persister->method('load')->willReturn($session);
        $persister->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (string $actualToken, array $options, string $salesChannelId) use (&$persistedOptions, $token): void {
                static::assertSame($token, $actualToken);
                static::assertSame(TestDefaults::SALES_CHANNEL, $salesChannelId);
                $persistedOptions[] = $options;
            });

        $factory = $this->createMock(SalesChannelContextFactory::class);
        $factory->expects($this->exactly(3))
            ->method('create')
            ->willReturnCallback(function (string $actualToken, string $salesChannelId, array $options) use ($token, $session, $context): SalesChannelContext {
                static $call = 0;

                static::assertSame($token, $actualToken);
                static::assertSame(TestDefaults::SALES_CHANNEL, $salesChannelId);

                if ($call++ === 0) {
                    static::assertSame($session, $options);

                    throw SalesChannelException::providedLanguageNotAvailable($session[SalesChannelContextService::LANGUAGE_ID], [Defaults::LANGUAGE_SYSTEM]);
                }

                if ($call === 2) {
                    static::assertArrayNotHasKey(SalesChannelContextService::LANGUAGE_ID, $options);
                    static::assertSame($session[SalesChannelContextService::CURRENCY_ID], $options[SalesChannelContextService::CURRENCY_ID]);

                    throw SalesChannelException::currencyNotFound($session[SalesChannelContextService::CURRENCY_ID]);
                }

                static::assertArrayNotHasKey(SalesChannelContextService::LANGUAGE_ID, $options);
                static::assertArrayNotHasKey(SalesChannelContextService::CURRENCY_ID, $options);

                return $context;
            });

        $service = $this->createContextService($factory, $persister, $token);

        static::assertSame($context, $service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $token)));
        static::assertSame([
            [SalesChannelContextService::LANGUAGE_ID => null],
            [SalesChannelContextService::CURRENCY_ID => null],
        ], $persistedOptions);
    }

    public function testDoesNotFallbackForExplicitLanguage(): void
    {
        $token = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $exception = SalesChannelException::providedLanguageNotAvailable($languageId, [Defaults::LANGUAGE_SYSTEM]);

        $persister = static::createStub(SalesChannelContextPersister::class);
        $persister->method('load')->willReturn([SalesChannelContextService::LANGUAGE_ID => Uuid::randomHex()]);

        $factory = $this->createMock(SalesChannelContextFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($token, TestDefaults::SALES_CHANNEL, [SalesChannelContextService::LANGUAGE_ID => $languageId])
            ->willThrowException($exception);

        $service = $this->createContextService($factory, $persister, $token);

        $this->expectExceptionObject($exception);
        $service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $token, $languageId));
    }

    public function testDispatchesSalesChannelContextCreatedEvent(): void
    {
        $token = 'test-token';
        $context = Generator::generateSalesChannelContext();
        $session = ['foo' => 'bar'];

        $persister = static::createStub(SalesChannelContextPersister::class);
        $persister->method('load')->willReturn($session);

        $factory = $this->createMock(SalesChannelContextFactory::class);
        $factory->expects($this->once())
            ->method('create')
            ->with($token, TestDefaults::SALES_CHANNEL, $session)
            ->willReturn($context);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(new SalesChannelContextCreatedEvent($context, $token, $session));

        $this->setupSessionAndRequest();

        $service = new SalesChannelContextService(
            $factory,
            static::createStub(CartRuleLoader::class),
            $persister,
            static::createStub(CartService::class),
            $eventDispatcher,
            $this->requestStack,
        );

        $service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $token));
    }

    #[DataProvider('skipCartCalculationIfAlreadyDoneAndESISubrequestProvider')]
    public function testSkipCartCalculationIfAlreadyDoneAndESISubrequest(Request $request, bool $hasCart, bool $expectCalculation): void
    {
        $customerId = Uuid::randomHex();
        $token = Uuid::randomHex();
        $result = new RuleLoaderResult(new Cart($token), new RuleCollection());

        $persister = static::createStub(SalesChannelContextPersister::class);
        $persister->method('load')->willReturn(['expired' => false, SalesChannelContextService::CUSTOMER_ID => $customerId]);

        $context = Generator::generateSalesChannelContext();

        $factory = $this->createMock(SalesChannelContextFactory::class);
        $factory
            ->expects($this->once())
            ->method('create')
            ->willReturn($context);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('hasCart')
            ->with($token)
            ->willReturn($hasCart);

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);

        if ($expectCalculation) {
            $cartRuleLoader
                ->expects($this->once())
                ->method('loadByToken')
                ->with($context, $token)
                ->willReturn($result);

            $cartService
                ->expects($this->once())
                ->method('setCart')
                ->with($result->getCart());
        } else {
            $cartRuleLoader
                ->expects($this->never())
                ->method(static::anything());

            $cartService
                ->expects($this->never())
                ->method('setCart');
        }

        $session = new Session(new MockArraySessionStorage());
        $session->set(SalesChannelContextService::RULE_IDS, ['rule-1', 'rule-2']);
        $session->set(SalesChannelContextService::AREA_RULE_IDS, [RuleAreas::PRODUCT_AREA => ['rule-1'], RuleAreas::PROMOTION_AREA => ['rule-2']]);

        $request->setSession($session);
        $this->requestStack->push($request);

        $service = new SalesChannelContextService(
            $factory,
            $cartRuleLoader,
            $persister,
            $cartService,
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );

        $context = $service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $token, Defaults::LANGUAGE_SYSTEM));

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
        $token = 'test-token';
        $originalContext = new Context(new SystemSource());
        $originalContext->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('withPermissions')->willReturn(static::createStub(RuleLoaderResult::class));
        $context->expects($this->once())
            ->method('addState')
            ->with(Context::ELASTICSEARCH_EXPLAIN_MODE);
        $session = [
            'foo' => 'bar',
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'originalContext' => $originalContext,
        ];

        $persister = static::createStub(SalesChannelContextPersister::class);
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
            static::createStub(CartRuleLoader::class),
            $persister,
            static::createStub(CartService::class),
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
        $token = Uuid::randomHex();
        $ruleIds = ['rule-1', 'rule-2', 'rule-3'];

        $persister = static::createStub(SalesChannelContextPersister::class);
        $persister->method('load')->willReturn(['expired' => false, SalesChannelContextService::CUSTOMER_ID => Uuid::randomHex()]);

        $context = $this->createMock(SalesChannelContext::class);
        $factory = $this->createMock(SalesChannelContextFactory::class);
        $factory
            ->expects($this->once())
            ->method('create')
            ->willReturn($context);

        $cartService = $this->createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('hasCart')
            ->with($token)
            ->willReturn(true);

        $context
            ->expects($this->once())
            ->method('setRuleIds')
            ->with($ruleIds);

        $cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $cartRuleLoader
            ->expects($this->never())
            ->method('loadByToken');
        $cartService
            ->expects($this->never())
            ->method('setCart');

        $this->setupSessionAndRequest([
            'sw-rule-ids' => $ruleIds,
        ], [
            '_esi' => true,
        ]);

        $service = new SalesChannelContextService(
            $factory,
            $cartRuleLoader,
            $persister,
            $cartService,
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );

        $service->get(new SalesChannelContextServiceParameters(TestDefaults::SALES_CHANNEL, $token, Defaults::LANGUAGE_SYSTEM));
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

    private function createContextService(SalesChannelContextFactory $factory, SalesChannelContextPersister $persister, string $token): SalesChannelContextService
    {
        $ruleLoader = static::createStub(CartRuleLoader::class);
        $ruleLoader->method('loadByToken')->willReturn(new RuleLoaderResult(new Cart($token), new RuleCollection()));

        return new SalesChannelContextService(
            $factory,
            $ruleLoader,
            $persister,
            static::createStub(CartService::class),
            static::createStub(EventDispatcherInterface::class),
            $this->requestStack,
        );
    }
}
