<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Extension\CheckoutDraftRouteExtension;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Gateway\Context\SalesChannel\AbstractContextGatewayRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Bundles a change of the checkout context with the recalculated result, so a headless checkout needs one request per change.
 * Every part is produced by the existing route for it.
 *
 * @internal
 */
#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CheckoutDraftRoute
{
    /**
     * The fields of `PATCH /store-api/context`, the change is delegated to that route.
     */
    private const CONTEXT_FIELDS = [
        SalesChannelContextService::SHIPPING_METHOD_ID,
        SalesChannelContextService::PAYMENT_METHOD_ID,
        SalesChannelContextService::BILLING_ADDRESS_ID,
        SalesChannelContextService::SHIPPING_ADDRESS_ID,
        SalesChannelContextService::COUNTRY_ID,
        SalesChannelContextService::COUNTRY_STATE_ID,
        SalesChannelContextService::CURRENCY_ID,
        SalesChannelContextService::LANGUAGE_ID,
        'shippingAddress',
        'billingAddress',
    ];

    /**
     * Changes that calculate the cart for another address or method count against the rate limit, switching currency or language does not.
     */
    private const RATE_LIMITED_FIELDS = [
        SalesChannelContextService::SHIPPING_METHOD_ID,
        SalesChannelContextService::PAYMENT_METHOD_ID,
        SalesChannelContextService::BILLING_ADDRESS_ID,
        SalesChannelContextService::SHIPPING_ADDRESS_ID,
        SalesChannelContextService::COUNTRY_ID,
        SalesChannelContextService::COUNTRY_STATE_ID,
        'shippingAddress',
        'billingAddress',
    ];

    private const FLAGS = ['methods', 'shippingCosts', 'checkoutGateway', 'taxed', 'contextGateway', 'appName'];

    /**
     * Read from the body by every Store API response (`includes`, `excludes`) and by the app Context Gateway (`data`).
     */
    private const PASSED_ON_FIELDS = ['includes', 'excludes', 'data'];

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly AbstractContextGatewayRoute $contextGatewayRoute,
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly CartService $cartService,
        private readonly TaxProviderProcessor $taxProviderProcessor,
        private readonly AbstractShippingMethodRoute $shippingMethodRoute,
        private readonly AbstractPaymentMethodRoute $paymentMethodRoute,
        private readonly AbstractCheckoutGatewayRoute $checkoutGatewayRoute,
        private readonly AbstractShippingCostRoute $shippingCostRoute,
        private readonly RateLimiter $rateLimiter,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    #[Route(
        path: '/store-api/checkout/draft',
        name: 'store-api.checkout.draft',
        methods: [Request::METHOD_GET, Request::METHOD_PATCH]
    )]
    public function draft(Request $request, SalesChannelContext $context): CheckoutDraftRouteResponse
    {
        return $this->extensions->publish(
            name: CheckoutDraftRouteExtension::NAME,
            extension: new CheckoutDraftRouteExtension($request, $context),
            function: $this->_draft(...),
        );
    }

    private function _draft(Request $request, SalesChannelContext $context): CheckoutDraftRouteResponse
    {
        if ($request->isMethod(Request::METHOD_PATCH)) {
            $context = $this->update($request, $context);
        } elseif ($this->flag($request, 'contextGateway', false)) {
            // the context gateway can change the context and the token, which a GET must never do
            throw CartException::invalidRequestParameter('contextGateway');
        }

        $cart = $this->cartService->getCart($context->getToken(), $context);
        if ($this->flag($request, 'taxed', false)) {
            // the same tax provider run as `GET /store-api/checkout/cart?taxed=1`
            $this->taxProviderProcessor->process($cart, $context);
        }

        $withMethods = $this->flag($request, 'methods', true);

        $shippingMethods = null;
        $paymentMethods = null;
        if ($withMethods) {
            $shippingCriteria = new Criteria();
            $shippingCriteria->getAssociation('prices')
                ->addFilter(new EqualsAnyFilter('ruleId', [null, ...$context->getRuleIds()]));

            $shippingMethods = $this->shippingMethodRoute->load(new Request(['onlyAvailable' => true]), $context, $shippingCriteria)->getShippingMethods();
            $paymentMethods = $this->paymentMethodRoute->load(new Request(['onlyAvailable' => true]), $context, new Criteria())->getPaymentMethods();
        }

        if ($this->flag($request, 'checkoutGateway', false)) {
            // also adds the cart errors for methods the apps block, which is why it runs without the method lists as well
            $gateway = $this->checkoutGatewayRoute->load(new Request(), $cart, $context);
            $shippingMethods = $shippingMethods?->filter(static fn (ShippingMethodEntity $method) => $gateway->getShippingMethods()->has($method->getId()));
            $paymentMethods = $paymentMethods?->filter(static fn (PaymentMethodEntity $method) => $gateway->getPaymentMethods()->has($method->getId()));
        }

        // the same calculation as `/store-api/shipping-cost/cart`, which asks the checkout gateway for the available methods itself
        $shippingCosts = $this->flag($request, 'shippingCosts', false)
            ? $this->shippingCostRoute->shippingCostsCart($cart, $context)->getShippingCosts()
            : null;

        $response = new CheckoutDraftRouteResponse(new CheckoutDraft(
            cart: $cart,
            prospectiveCustomer: $context->getProspectiveCustomer(),
            shippingMethod: $context->getShippingMethod(),
            paymentMethod: $context->getPaymentMethod(),
            currency: $context->getCurrency(),
            language: $context->getLanguageInfo(),
            shippingMethods: $shippingMethods,
            paymentMethods: $paymentMethods,
            shippingCosts: $shippingCosts,
        ));

        // the context gateway can replace the token, the client has to continue with the new one
        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $context->getToken());

        return $response;
    }

    private function update(Request $request, SalesChannelContext $context): SalesChannelContext
    {
        $body = $request->request->all();

        // unlike the context route, unknown fields fail, so a client never gets a success for a change that did not happen
        foreach (array_keys($body) as $field) {
            if (!\in_array($field, [...self::CONTEXT_FIELDS, ...self::FLAGS, ...self::PASSED_ON_FIELDS], true)) {
                throw CartException::invalidRequestParameter((string) $field);
            }
        }

        $changes = array_intersect_key($body, array_flip(self::CONTEXT_FIELDS));

        if (array_intersect_key($changes, array_flip(self::RATE_LIMITED_FIELDS)) !== []) {
            // keyed by token, as server-side rendered storefronts send all visitors' requests from one IP
            $this->rateLimiter->ensureAccepted(RateLimiter::CHECKOUT_DRAFT, $context->getToken());
        }

        if ($changes !== []) {
            $token = $this->contextSwitchRoute->switchContext(new RequestDataBag($changes), $context)->getToken();
            $context = $this->reloadContext($request, $context, $token);
        }

        if ($this->flag($request, 'contextGateway', false)) {
            $gatewayRequest = new Request(request: [...$body, 'appName' => RequestParamHelper::get($request, 'appName')]);
            $cart = $this->cartService->getCart($context->getToken(), $context);

            // without commands that change the context, the gateway response has no token and the context stays as it is
            $token = $this->contextGatewayRoute->load($gatewayRequest, $cart, $context)->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
            if ($token !== null && $token !== '') {
                $context = $this->reloadContext($request, $context, $token);
            }
        }

        return $context;
    }

    /**
     * Rebuilds the context with the parameters of `SalesChannelRequestContextResolver`, so that e.g. the language and currency headers
     * apply to the result of a `PATCH` like to a `GET`. The context the switch route rebuilt is built from the token only.
     */
    private function reloadContext(Request $request, SalesChannelContext $context, string $token): SalesChannelContext
    {
        $languageId = (string) $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID);
        $currencyId = (string) $request->headers->get(PlatformRequest::HEADER_CURRENCY_ID);
        $source = $context->getContext()->getSource();

        return $this->contextService->get(new SalesChannelContextServiceParameters(
            $context->getSalesChannelId(),
            $token,
            $languageId !== '' ? $languageId : null,
            $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID),
            $request->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID),
            $source instanceof AdminSalesChannelApiSource ? $source->getOriginalContext() : null,
            null,
            $context->getImitatingUserId(),
            $currencyId !== '' ? $currencyId : null,
        ));
    }

    private function flag(Request $request, string $name, bool $default): bool
    {
        return filter_var(RequestParamHelper::get($request, $name, $default), \FILTER_VALIDATE_BOOL);
    }
}
