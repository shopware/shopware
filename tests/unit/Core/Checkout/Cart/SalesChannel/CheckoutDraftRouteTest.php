<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingCostCollection;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractShippingCostRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\SalesChannel\CheckoutDraftRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\ShippingCostRouteResponse;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\ProspectiveAddress;
use Shopware\Core\Checkout\Customer\ProspectiveCustomer;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Gateway\Context\SalesChannel\AbstractContextGatewayRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CheckoutDraftRoute::class)]
class CheckoutDraftRouteTest extends TestCase
{
    private AbstractContextSwitchRoute&Stub $contextSwitchRoute;

    private AbstractContextGatewayRoute&Stub $contextGatewayRoute;

    private SalesChannelContextServiceInterface&Stub $contextService;

    private TaxProviderProcessor&Stub $taxProviderProcessor;

    private AbstractShippingMethodRoute&Stub $shippingMethodRoute;

    private AbstractPaymentMethodRoute&Stub $paymentMethodRoute;

    private AbstractCheckoutGatewayRoute&Stub $checkoutGatewayRoute;

    private AbstractShippingCostRoute&Stub $shippingCostRoute;

    private RateLimiter&Stub $rateLimiter;

    private SalesChannelContext $context;

    private Cart $cart;

    private ShippingMethodEntity $shippingMethod;

    private PaymentMethodEntity $paymentMethod;

    protected function setUp(): void
    {
        $this->context = Generator::generateSalesChannelContext(token: 'token');
        $this->cart = new Cart('token');

        $this->contextSwitchRoute = static::createStub(AbstractContextSwitchRoute::class);
        $this->contextGatewayRoute = static::createStub(AbstractContextGatewayRoute::class);
        $this->contextService = static::createStub(SalesChannelContextServiceInterface::class);
        $this->taxProviderProcessor = static::createStub(TaxProviderProcessor::class);
        $this->checkoutGatewayRoute = static::createStub(AbstractCheckoutGatewayRoute::class);
        $this->shippingCostRoute = static::createStub(AbstractShippingCostRoute::class);
        $this->rateLimiter = static::createStub(RateLimiter::class);

        $this->shippingMethod = new ShippingMethodEntity();
        $this->shippingMethod->setId('shipping-method-id');
        $this->shippingMethodRoute = static::createStub(AbstractShippingMethodRoute::class);
        $this->shippingMethodRoute->method('load')->willReturn(new ShippingMethodRouteResponse(
            new EntitySearchResult(ShippingMethodDefinition::ENTITY_NAME, 1, new ShippingMethodCollection([$this->shippingMethod]), null, new Criteria(), $this->context->getContext())
        ));

        $this->paymentMethod = new PaymentMethodEntity();
        $this->paymentMethod->setId('payment-method-id');
        $this->paymentMethodRoute = static::createStub(AbstractPaymentMethodRoute::class);
        $this->paymentMethodRoute->method('load')->willReturn(new PaymentMethodRouteResponse(
            new EntitySearchResult(PaymentMethodDefinition::ENTITY_NAME, 1, new PaymentMethodCollection([$this->paymentMethod]), null, new Criteria(), $this->context->getContext())
        ));
    }

    public function testGetReturnsTheDraftOfTheCurrentContextWithoutChangingIt(): void
    {
        $this->contextSwitchRoute = $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->never())->method('switchContext');
        $this->contextGatewayRoute = $contextGatewayRoute = $this->createMock(AbstractContextGatewayRoute::class);
        $contextGatewayRoute->expects($this->never())->method('load');
        $this->checkoutGatewayRoute = $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute->expects($this->never())->method('load');
        $this->shippingCostRoute = $shippingCostRoute = $this->createMock(AbstractShippingCostRoute::class);
        $shippingCostRoute->expects($this->never())->method('shippingCostsCart');
        $this->taxProviderProcessor = $taxProviderProcessor = $this->createMock(TaxProviderProcessor::class);
        $taxProviderProcessor->expects($this->never())->method('process');
        $this->rateLimiter = $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->never())->method('ensureAccepted');

        $response = $this->createRoute()->draft(new Request(), $this->context);
        $draft = $response->getDraft();

        static::assertSame($this->cart, $draft->cart);
        static::assertSame($this->context->getShippingMethod(), $draft->shippingMethod);
        static::assertSame($this->context->getPaymentMethod(), $draft->paymentMethod);
        static::assertSame([$this->shippingMethod->getId() => $this->shippingMethod->getId()], $draft->shippingMethods?->getIds());
        static::assertSame([$this->paymentMethod->getId() => $this->paymentMethod->getId()], $draft->paymentMethods?->getIds());
        static::assertNull($draft->shippingCosts);
        static::assertSame('token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testMethodListsCanBeSkipped(): void
    {
        $this->shippingMethodRoute = $shippingMethodRoute = $this->createMock(AbstractShippingMethodRoute::class);
        $shippingMethodRoute->expects($this->never())->method('load');
        $this->paymentMethodRoute = $paymentMethodRoute = $this->createMock(AbstractPaymentMethodRoute::class);
        $paymentMethodRoute->expects($this->never())->method('load');

        $draft = $this->createRoute()->draft(new Request(['methods' => 'false']), $this->context)->getDraft();

        static::assertNull($draft->shippingMethods);
        static::assertNull($draft->paymentMethods);
    }

    public function testOptionalPartsRunTheirExistingMechanism(): void
    {
        $this->taxProviderProcessor = $taxProviderProcessor = $this->createMock(TaxProviderProcessor::class);
        $taxProviderProcessor->expects($this->once())->method('process')->with($this->cart, $this->context);

        $shippingCosts = new ShippingCostCollection();
        $this->shippingCostRoute = $shippingCostRoute = $this->createMock(AbstractShippingCostRoute::class);
        $shippingCostRoute->expects($this->once())
            ->method('shippingCostsCart')
            ->with($this->cart, $this->context)
            ->willReturn(new ShippingCostRouteResponse($shippingCosts));

        // the apps keep the payment method, but block the shipping method
        $this->checkoutGatewayRoute = $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute->expects($this->once())
            ->method('load')
            ->willReturn(new CheckoutGatewayRouteResponse(new PaymentMethodCollection([$this->paymentMethod]), new ShippingMethodCollection(), new ErrorCollection()));

        $draft = $this->createRoute()->draft(new Request(['taxed' => '1', 'shippingCosts' => '1', 'checkoutGateway' => '1']), $this->context)->getDraft();

        static::assertSame($shippingCosts, $draft->shippingCosts);
        static::assertSame([], $draft->shippingMethods?->getIds());
        static::assertSame([$this->paymentMethod->getId() => $this->paymentMethod->getId()], $draft->paymentMethods?->getIds());
    }

    public function testGetRejectsTheContextGateway(): void
    {
        $this->contextGatewayRoute = $contextGatewayRoute = $this->createMock(AbstractContextGatewayRoute::class);
        $contextGatewayRoute->expects($this->never())->method('load');

        $this->expectExceptionObject(CartException::invalidRequestParameter('contextGateway'));

        $this->createRoute()->draft(new Request(['contextGateway' => '1', 'appName' => 'App']), $this->context);
    }

    public function testPatchRejectsUnknownFields(): void
    {
        $this->contextSwitchRoute = $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->never())->method('switchContext');

        $this->expectExceptionObject(CartException::invalidRequestParameter('customerGroupId'));

        $this->createRoute()->draft($this->createPatchRequest(['countryId' => 'country-id', 'customerGroupId' => 'group-id']), $this->context);
    }

    public function testPatchDelegatesTheChangeAndReturnsTheDraftOfTheRebuiltContext(): void
    {
        $request = $this->createPatchRequest(['shippingAddress' => ['countryId' => 'country-id'], 'methods' => false]);

        $changedContext = Generator::generateSalesChannelContext(token: 'token');
        $changedContext->setProspectiveCustomer(new ProspectiveCustomer(new ProspectiveAddress('country-id'), null, new CustomerEntity()));

        $this->contextSwitchRoute = $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())
            ->method('switchContext')
            ->with(static::equalTo(new RequestDataBag(['shippingAddress' => ['countryId' => 'country-id']])), $this->context)
            ->willReturn(new ContextTokenResponse('token'));
        $this->contextService = $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new SalesChannelContextServiceParameters($this->context->getSalesChannelId(), 'token')))
            ->willReturn($changedContext);

        $draft = $this->createRoute()->draft($request, $this->context)->getDraft();

        static::assertSame($changedContext->getProspectiveCustomer(), $draft->prospectiveCustomer);
    }

    public function testPatchRebuildsTheContextWithTheParametersOfTheRequest(): void
    {
        $request = $this->createPatchRequest(['currencyId' => 'stored-currency-id', 'methods' => false]);
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, 'header-language-id');
        $request->headers->set(PlatformRequest::HEADER_CURRENCY_ID, 'header-currency-id');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID, 'domain-id');
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, 'domain-currency-id');

        $this->contextSwitchRoute->method('switchContext')->willReturn(new ContextTokenResponse('token'));
        $this->contextService = $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new SalesChannelContextServiceParameters(
                salesChannelId: $this->context->getSalesChannelId(),
                token: 'token',
                languageId: 'header-language-id',
                currencyId: 'domain-currency-id',
                domainId: 'domain-id',
                overwriteCurrencyId: 'header-currency-id',
            )))
            ->willReturn($this->context);

        $this->createRoute()->draft($request, $this->context);
    }

    public function testPatchAcceptsTheFieldsOfTheResponseAndTheContextGateway(): void
    {
        $body = ['countryId' => 'country-id', 'includes' => ['checkout_draft' => ['cart']], 'excludes' => [], 'data' => ['key' => 'value'], 'contextGateway' => true, 'appName' => 'App'];

        $this->contextSwitchRoute = $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute->expects($this->once())
            ->method('switchContext')
            ->with(static::equalTo(new RequestDataBag(['countryId' => 'country-id'])))
            ->willReturn(new ContextTokenResponse('token'));
        $this->contextService->method('get')->willReturn($this->context);
        $this->contextGatewayRoute = $contextGatewayRoute = $this->createMock(AbstractContextGatewayRoute::class);
        $contextGatewayRoute->expects($this->once())
            ->method('load')
            ->with(static::callback(static fn (Request $request): bool => $request->request->all('data') === ['key' => 'value']))
            ->willReturn(new ContextTokenResponse('token'));

        $this->createRoute()->draft($this->createPatchRequest($body), $this->context);
    }

    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('rateLimitProvider')]
    public function testOnlyChangesOfAnAddressOrAMethodAreRateLimited(array $body, bool $limited): void
    {
        $this->contextSwitchRoute->method('switchContext')->willReturn(new ContextTokenResponse('token'));
        $this->contextService->method('get')->willReturn($this->context);

        $this->rateLimiter = $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($limited ? $this->once() : $this->never())
            ->method('ensureAccepted')
            ->with(RateLimiter::CHECKOUT_DRAFT, 'token');

        $this->createRoute()->draft($this->createPatchRequest($body), $this->context);
    }

    public static function rateLimitProvider(): \Generator
    {
        yield 'an address change recalculates the cart for another address' => [['shippingAddress' => ['countryId' => 'country-id']], true];
        yield 'a shipping method change recalculates the cart' => [['shippingMethodId' => 'shipping-method-id'], true];
        yield 'a currency switch is not limited' => [['currencyId' => 'currency-id'], false];
        yield 'reading the draft with flags only is not limited' => [['shippingCosts' => false], false];
    }

    public function testContextGatewayCanReplaceTheToken(): void
    {
        $this->contextGatewayRoute = $contextGatewayRoute = $this->createMock(AbstractContextGatewayRoute::class);
        $contextGatewayRoute->expects($this->once())
            ->method('load')
            ->with(static::callback(static fn (Request $request): bool => $request->request->get('appName') === 'App'))
            ->willReturn(new ContextTokenResponse('new-token'));

        $newContext = Generator::generateSalesChannelContext(token: 'new-token');
        $this->contextService = $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService->expects($this->once())
            ->method('get')
            ->with(static::equalTo(new SalesChannelContextServiceParameters($this->context->getSalesChannelId(), 'new-token')))
            ->willReturn($newContext);

        $response = $this->createRoute()->draft($this->createPatchRequest(['contextGateway' => true, 'appName' => 'App']), $this->context);

        static::assertSame('new-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testContextGatewayWithoutChangesKeepsTheToken(): void
    {
        // the command executor removes the token header when no command changes the context
        $gatewayResponse = new ContextTokenResponse('token');
        $gatewayResponse->headers->remove(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $this->contextGatewayRoute->method('load')->willReturn($gatewayResponse);

        $this->contextService = $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService->expects($this->never())->method('get');

        $response = $this->createRoute()->draft($this->createPatchRequest(['contextGateway' => true, 'appName' => 'App']), $this->context);

        static::assertSame($this->context->getToken(), $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('', $this->context->getToken());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function createPatchRequest(array $body): Request
    {
        $request = new Request(request: $body);
        $request->setMethod(Request::METHOD_PATCH);

        return $request;
    }

    private function createRoute(): CheckoutDraftRoute
    {
        $cartService = static::createStub(CartService::class);
        $cartService->method('getCart')->willReturn($this->cart);

        return new CheckoutDraftRoute(
            $this->contextSwitchRoute,
            $this->contextGatewayRoute,
            $this->contextService,
            $cartService,
            $this->taxProviderProcessor,
            $this->shippingMethodRoute,
            $this->paymentMethodRoute,
            $this->checkoutGatewayRoute,
            $this->shippingCostRoute,
            $this->rateLimiter,
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
