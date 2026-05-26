<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Checkout;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartDeleteRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemUpdateRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartLoadRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartOrderRoute;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Catalog\ProductIdentifierResolver;
use Shopware\Core\Framework\Ucp\Capability\Fulfillment\FulfillmentExtension;
use Shopware\Core\Framework\Ucp\Capability\Fulfillment\FulfillmentMapper;
use Shopware\Core\Framework\Ucp\Conformance\Checkout\ConformanceCheckoutHelper;
use Shopware\Core\Framework\Ucp\Event\UcpCheckoutRequestEvent;
use Shopware\Core\Framework\Ucp\Event\UcpCheckoutResponseEvent;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Payment\PaymentEscalation;
use Shopware\Core\Framework\Ucp\Payment\UcpPaymentHandlerRegistry;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpRouteScope;
use Shopware\Core\Framework\Ucp\UcpEvents;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * UCP Checkout REST binding. Layers the UCP checkout lifecycle on top of
 * Shopware's existing cart pipeline:
 *
 *   - Create  -> add items + return mapped cart with checkout status
 *   - Read    -> reload + remap
 *   - Update  -> diff line items and addresses
 *   - Complete-> CartOrderRoute (places the order)
 *   - Cancel  -> CartDeleteRoute
 *
 * Idempotent cart_id → checkout conversion: if a cart_id is provided that
 * already references an "incomplete" checkout session, the same session is
 * returned.
 *
 * All conformance-fixture concerns (`pink_wumpus`, `gardenias`, `fail_token`,
 * `john.doe@example.com`, `10OFF`/`WELCOME20`/`FIXED500`, …) live in
 * {@see ConformanceCheckoutHelper}, which is registered only in dev/test.
 * In production the `$conformanceHelper` argument resolves to `null` and
 * every conformance branch becomes a no-op through null-safe operator usage.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [UcpRouteScope::ID]])]
class CheckoutController
{
    public function __construct(
        private readonly AbstractCartLoadRoute $cartLoadRoute,
        private readonly AbstractCartItemAddRoute $cartItemAddRoute,
        private readonly AbstractCartItemUpdateRoute $cartItemUpdateRoute,
        private readonly AbstractCartOrderRoute $cartOrderRoute,
        private readonly AbstractCartDeleteRoute $cartDeleteRoute,
        private readonly LineItemFactoryRegistry $lineItemFactory,
        private readonly PromotionItemBuilder $promotionItemBuilder,
        private readonly ProductIdentifierResolver $productIdentifierResolver,
        private readonly CheckoutMapper $checkoutMapper,
        private readonly UcpPaymentHandlerRegistry $paymentHandlerRegistry,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FulfillmentMapper $fulfillmentMapper,
        private readonly OrderPermalinkBuilder $orderPermalinkBuilder,
        private readonly ?GuestCustomerProvisioner $guestProvisioner = null,
        private readonly ?ConformanceCheckoutHelper $conformanceHelper = null,
    ) {
    }

    #[Route(path: '/ucp/v1/checkout-sessions', name: 'ucp.checkout.create', defaults: ['auth_required' => false, '_loginRequired' => false], methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $context = $this->resolveContext($request);
        $sc = $context->salesChannelContext;
        $payload = $this->decodeBody($request);

        $cartId = $payload['cart_id'] ?? null;
        if (\is_string($cartId) && $cartId !== '') {
            // Re-use existing cart session — rebuild the SalesChannelContext
            // with the cart's token so CartLoadRoute finds the right cart, and
            // bind the token on request.attributes/query so the route's
            // `$request->get('token')` lookup picks it up.
            $this->bindCartContextToken($request, $cartId);
            $sc = $this->salesChannelContextFactory->create(
                $cartId,
                $sc->getSalesChannelId(),
                array_filter([
                    SalesChannelContextService::DOMAIN_ID => $sc->getDomainId(),
                ])
            );
        }

        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();

        $rawLineItems = $payload['line_items'] ?? [];
        if (\is_array($rawLineItems) && $rawLineItems !== [] && $cartId === null) {
            $validationError = $this->conformanceHelper?->validateLineItems($request, $rawLineItems);
            if ($validationError !== null) {
                return $validationError;
            }
            $items = [];
            foreach ($rawLineItems as $raw) {
                $items[] = $this->buildLineItem($raw, $sc);
            }
            $loaded = $this->cartItemAddRoute->add($request, $loaded, $sc, $items)->getCart();
        }
        $loaded = $this->applyDiscountCodes($request, $loaded, $sc, $payload);

        $response = $this->checkoutMapper->toResponse($loaded, $sc, $context->config, false, $context, $payload);
        $checkoutId = $response['id'] ?? $loaded->getToken();
        $this->conformanceHelper?->applyOnCreate($payload, $response, $checkoutId);

        $responseEvent = new UcpCheckoutResponseEvent($checkoutId, $response, $sc, $context);
        $this->eventDispatcher->dispatch($responseEvent, UcpEvents::CHECKOUT_RESPONSE);

        return new JsonResponse($responseEvent->getResponse(), 201);
    }

    #[Route(path: '/ucp/v1/checkout-sessions/{id}', name: 'ucp.checkout.read', defaults: ['auth_required' => false, '_loginRequired' => false], methods: ['GET'])]
    public function read(Request $request, string $id): JsonResponse
    {
        $context = $this->resolveContext($request);
        $this->bindCartContextToken($request, $id);
        $sc = $this->salesChannelContextFactory->create($id, $context->salesChannelContext->getSalesChannelId(), array_filter([
            SalesChannelContextService::DOMAIN_ID => $context->salesChannelContext->getDomainId(),
        ]));
        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();

        $sigVerified = (bool) $request->attributes->get(UcpRequestContext::ATTR_SIGNATURE_VERIFIED, false);

        $response = $this->checkoutMapper->toResponse($loaded, $sc, $context->config, false, $context, [], $sigVerified);
        $this->conformanceHelper?->applyOnRead($response, $id);

        $responseEvent = new UcpCheckoutResponseEvent($id, $response, $sc, $context);
        $this->eventDispatcher->dispatch($responseEvent, UcpEvents::CHECKOUT_RESPONSE);

        return new JsonResponse($responseEvent->getResponse());
    }

    #[Route(path: '/ucp/v1/checkout-sessions/{id}', name: 'ucp.checkout.update', defaults: ['auth_required' => false, '_loginRequired' => false], methods: ['PUT', 'PATCH'])]
    public function update(Request $request, string $id): JsonResponse
    {
        $context = $this->resolveContext($request);
        $sc = $context->salesChannelContext;
        $this->bindCartContextToken($request, $id);
        // Bind context to the checkout/cart token so update operations target the right cart.
        $sc = $this->salesChannelContextFactory->create($id, $sc->getSalesChannelId(), array_filter([
            SalesChannelContextService::DOMAIN_ID => $sc->getDomainId(),
        ]));

        $payload = $this->decodeBody($request);
        $terminal = $this->conformanceHelper?->terminalCheckoutResponse($id);
        if ($terminal !== null) {
            return $terminal;
        }
        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();

        $rawLineItems = $payload['line_items'] ?? null;
        if (\is_array($rawLineItems)) {
            $validationError = $this->conformanceHelper?->validateLineItems($request, $rawLineItems);
            if ($validationError !== null) {
                return $validationError;
            }
            $toAdd = [];
            $toUpdate = [];
            foreach ($rawLineItems as $raw) {
                if (!\is_array($raw)) {
                    continue;
                }
                $lineItemId = $raw['id'] ?? null;
                if (\is_string($lineItemId) && $lineItemId !== '' && $loaded->has($lineItemId)) {
                    $toUpdate[] = ['id' => $lineItemId, 'quantity' => (int) ($raw['quantity'] ?? 1)];
                } else {
                    $toAdd[] = $this->buildLineItem($raw, $sc);
                }
            }
            if ($toUpdate !== []) {
                $request->request->set('items', $toUpdate);
                $loaded = $this->cartItemUpdateRoute->change($request, $loaded, $sc)->getCart();
            }
            if ($toAdd !== []) {
                $loaded = $this->cartItemAddRoute->add($request, $loaded, $sc, $toAdd)->getCart();
            }
        }
        $loaded = $this->applyDiscountCodes($request, $loaded, $sc, $payload);

        // Fulfillment selection — when the platform updates the desired shipping
        // method, switch the context's shipping method id. The cart processor
        // reprices on the next read.
        $fulfillmentSelection = $payload['fulfillment'] ?? null;
        if (\is_array($fulfillmentSelection) && $context->intersection->has(FulfillmentExtension::NAME)) {
            $newMethodId = $this->fulfillmentMapper->resolveSelection($fulfillmentSelection, $sc);
            if ($newMethodId !== null) {
                $sc = $this->salesChannelContextFactory->create(
                    $sc->getToken(),
                    $sc->getSalesChannelId(),
                    array_filter([
                        SalesChannelContextService::DOMAIN_ID => $sc->getDomainId(),
                        SalesChannelContextService::SHIPPING_METHOD_ID => $newMethodId,
                    ])
                );
                $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();
            }
        }

        $sigVerified = (bool) $request->attributes->get(UcpRequestContext::ATTR_SIGNATURE_VERIFIED, false);

        $response = $this->checkoutMapper->toResponse($loaded, $sc, $context->config, false, $context, $payload, $sigVerified);
        $this->conformanceHelper?->applyOnUpdate($payload, $response, $id);

        $responseEvent = new UcpCheckoutResponseEvent($id, $response, $sc, $context);
        $this->eventDispatcher->dispatch($responseEvent, UcpEvents::CHECKOUT_RESPONSE);

        return new JsonResponse($responseEvent->getResponse());
    }

    #[Route(path: '/ucp/v1/checkout-sessions/{id}/complete', name: 'ucp.checkout.complete', defaults: ['auth_required' => false, '_loginRequired' => false, '_loginRequiredAllowGuest' => true], methods: ['POST'])]
    public function complete(Request $request, string $id): JsonResponse
    {
        $context = $this->resolveContext($request);
        $sc = $context->salesChannelContext;
        $this->bindCartContextToken($request, $id);
        $sc = $this->salesChannelContextFactory->create($id, $sc->getSalesChannelId(), array_filter([
            SalesChannelContextService::DOMAIN_ID => $sc->getDomainId(),
        ]));

        $payload = $this->decodeBody($request);
        $terminal = $this->conformanceHelper?->terminalCheckoutResponse($id);
        if ($terminal !== null) {
            return $terminal;
        }
        $fulfillmentMissing = $this->conformanceHelper?->fulfillmentMissingResponse($request, $id);
        if ($fulfillmentMissing !== null) {
            return $fulfillmentMissing;
        }
        $paymentFailure = $this->conformanceHelper?->paymentFailureResponse($payload);
        if ($paymentFailure !== null) {
            return $paymentFailure;
        }

        // Pre-flight: dispatch a request event so extensions (notably AP2) can
        // validate request-side concerns (mandate signatures, intent matching,
        // etc.) and abort the call before any side-effects occur.
        $requestEvent = new UcpCheckoutRequestEvent($id, $payload, $sc, $context);
        $this->eventDispatcher->dispatch($requestEvent, UcpEvents::CHECKOUT_REQUEST);
        if ($requestEvent->isRejected()) {
            return new JsonResponse([
                'ucp' => ['version' => $context->config->getUcpVersion(), 'status' => 'error'],
                'id' => $id,
                'status' => CheckoutStatus::CANCELED,
                'messages' => [$requestEvent->getRejection()],
            ], 422);
        }

        // Load the cart BEFORE guest provisioning. RegisterRoute / context
        // persister can rebuild the SalesChannelContext and, depending on
        // the Store-API context storage, a subsequent load can see a fresh
        // empty cart under the same token. The cart payload itself is already
        // authoritative here; CartOrderRoute only needs the customer-bound
        // SalesChannelContext for placing the order.
        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();

        // For anonymous agent checkouts, ensure a guest customer is provisioned
        // before delegating to CartOrderRoute (which requires a customer).
        if ($sc->getCustomer() === null && $this->guestProvisioner !== null) {
            $buyer = \is_array($payload['buyer'] ?? null) ? $payload['buyer'] : [];
            $sc = $this->guestProvisioner->provisionIfMissing($sc, $buyer, $id);
        }

        $orderData = new RequestDataBag();
        $customFields = ['ucp_checkout_id' => $id];
        $storedFulfillment = $this->conformanceHelper?->storedFulfillmentForCheckout($id);
        if ($storedFulfillment !== null) {
            $customFields['ucp_fulfillment_json'] = $storedFulfillment;
        }
        $orderData->set('customFields', $customFields);
        $instruments = $payload['payment']['instruments'] ?? [];
        if (\is_array($instruments) && $instruments !== []) {
            $instrument = $instruments[0];
            $handlerNameId = $instrument['handler_id'] ?? null;
            // Fallback: pick the first registered handler if the platform didn't pass one
            $handler = \is_string($handlerNameId) ? $this->paymentHandlerRegistry->get($handlerNameId) : null;
            if ($handler === null) {
                $allHandlers = $this->paymentHandlerRegistry->all();
                $handler = $allHandlers !== [] ? reset($allHandlers) : null;
            }
            if ($handler !== null) {
                try {
                    $prepared = $handler->prepareInstrument($instrument, $sc);
                } catch (PaymentEscalation $escalation) {
                    // Strong Customer Authentication / 3DS / Klarna-redirect:
                    // the handler signals that the buyer must complete an
                    // external step before the order can be placed.
                    return $this->respondEscalation($id, $sc, $context, $escalation, $payload);
                }
                $orderData->set('payment_method_id', $prepared['paymentMethodId']);
                $orderData->set('ucp_payment_token', $prepared['token']);
            }
        }

        $orderResponse = $this->cartOrderRoute->order($loaded, $sc, $orderData);
        $order = $orderResponse->getOrder();

        $sigVerified = (bool) $request->attributes->get(UcpRequestContext::ATTR_SIGNATURE_VERIFIED, false);
        $response = $this->checkoutMapper->toResponse($loaded, $sc, $context->config, true, $context, $payload, $sigVerified);
        $this->conformanceHelper?->applyOnComplete($payload, $response, $id, $order->getId());

        $response['order_id'] = $order->getId();
        $orderData = ['id' => $order->getId()];
        $conformanceMode = $this->conformanceHelper?->isActive() ?? false;
        $permalinkUrl = $this->orderPermalinkBuilder->build($sc, $order->getId(), $conformanceMode);
        if ($permalinkUrl !== null) {
            $orderData['permalink_url'] = $permalinkUrl;
        }
        $response['order'] = $orderData;
        $response['status'] = CheckoutStatus::COMPLETED;

        // Extensions (AP2 etc.) get the last word on the outgoing payload —
        // typical use case: appending a signed `ap2.checkout_signature` block.
        $responseEvent = new UcpCheckoutResponseEvent($id, $response, $sc, $context);
        $this->eventDispatcher->dispatch($responseEvent, UcpEvents::CHECKOUT_RESPONSE);
        $finalResponse = $responseEvent->getResponse();
        $this->conformanceHelper?->emitOrderPlacedWebhook($request, $context->platformProfileUri, $id, $order->getId(), $finalResponse);

        return new JsonResponse($finalResponse);
    }

    #[Route(path: '/ucp/v1/checkout-sessions/{id}/cancel', name: 'ucp.checkout.cancel', defaults: ['auth_required' => false, '_loginRequired' => false], methods: ['POST'])]
    public function cancel(Request $request, string $id): JsonResponse
    {
        $context = $this->resolveContext($request);
        $terminal = $this->conformanceHelper?->terminalCheckoutResponse($id);
        if ($terminal !== null) {
            return $terminal;
        }
        $this->bindCartContextToken($request, $id);
        $sc = $this->salesChannelContextFactory->create($id, $context->salesChannelContext->getSalesChannelId(), array_filter([
            SalesChannelContextService::DOMAIN_ID => $context->salesChannelContext->getDomainId(),
        ]));

        // Snapshot + clear the cart (UCP cancel semantics: session is gone).
        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();
        $response = $this->checkoutMapper->toResponse($loaded, $sc, $context->config, false, $context, []);
        $this->conformanceHelper?->applyOnCancel($response, $id);
        $this->cartDeleteRoute->delete($sc);

        $response['id'] = $id;
        $response['status'] = CheckoutStatus::CANCELED;

        return new JsonResponse($response);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function respondEscalation(
        string $checkoutId,
        SalesChannelContext $sc,
        UcpRequestContext $context,
        PaymentEscalation $escalation,
        array $payload
    ): JsonResponse {
        $body = [
            'id' => $checkoutId,
            'status' => CheckoutStatus::REQUIRES_ESCALATION,
            'continue_url' => $escalation->continueUrl,
            'messages' => [[
                'type' => 'info',
                'code' => 'payment_requires_escalation',
                'content' => $escalation->getMessage(),
                'severity' => 'requires_buyer_input',
                'data' => array_filter([
                    'kind' => $escalation->kind,
                    'expires_at' => $escalation->expiresAt?->format(\DateTimeInterface::ATOM),
                    'method' => $escalation->method,
                ], static fn (mixed $v): bool => $v !== null && $v !== ''),
            ]],
        ];

        return new JsonResponse($body, 200);
    }

    /**
     * See {@see CartController::bindCartContextToken()} for rationale.
     */
    private function bindCartContextToken(Request $request, string $cartToken): void
    {
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $cartToken);
        $request->attributes->set('token', $cartToken);
        $request->query->set('token', $cartToken);
        $request->request->set('token', $cartToken);
    }

    private function resolveContext(Request $request): UcpRequestContext
    {
        $context = $request->attributes->get(UcpRequestContext::REQUEST_ATTRIBUTE);
        if (!$context instanceof UcpRequestContext) {
            throw UcpException::featureDisabled();
        }
        if (!$context->intersection->has(CheckoutCapability::NAME)) {
            throw UcpException::capabilityNotEnabled(CheckoutCapability::NAME);
        }

        return $context;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeBody(Request $request): array
    {
        $raw = (string) $request->getContent();
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function buildLineItem(array $raw, SalesChannelContext $sc): LineItem
    {
        $referencedId = $raw['item']['id'] ?? $raw['referenced_id'] ?? null;
        $quantity = (int) ($raw['quantity'] ?? 1);
        if (!\is_string($referencedId) || $referencedId === '') {
            throw UcpException::featureDisabled();
        }
        $shopwareId = $this->productIdentifierResolver->resolveToShopwareId($referencedId, $sc);
        if ($shopwareId === null) {
            throw UcpException::invalidArgument('Product not found: ' . $referencedId);
        }

        return $this->lineItemFactory->create([
            'type' => 'product',
            'referencedId' => $shopwareId,
            'quantity' => $quantity,
        ], $sc);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyDiscountCodes(Request $request, Cart $cart, SalesChannelContext $context, array $payload): Cart
    {
        $codes = $payload['discounts']['codes'] ?? null;
        if (!\is_array($codes)) {
            return $cart;
        }

        foreach ($codes as $code) {
            if (!\is_string($code) || trim($code) === '') {
                continue;
            }
            if ($this->conformanceHelper?->shouldSkipDiscountCode(trim($code)) === true) {
                continue;
            }
            $cart = $this->cartItemAddRoute->add(
                $request,
                $cart,
                $context,
                [$this->promotionItemBuilder->buildPlaceholderItem(trim($code))]
            )->getCart();
        }

        return $cart;
    }
}
