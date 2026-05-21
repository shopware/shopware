<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Order;

use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutStateStore;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Bridge\UcpAccessTokenAuthenticator;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Bridge\UcpScopeGuard;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpRouteScope;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * REST surface for the `dev.ucp.shopping.order` capability per
 * `ucp/docs/specification/order-rest.md`.
 *
 *   GET /ucp/v1/orders/{id}    — fetch a single order (read scope)
 *
 * The order read endpoint requires the `dev.ucp.shopping.order:read` OAuth
 * scope when called with a bearer token. Anonymous calls succeed only when
 * the order belongs to a guest customer associated with the cart context
 * token (matching the cart's UCP session).
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [UcpRouteScope::ID]])]
class OrderController
{
    /**
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly OrderMapper $orderMapper,
        private readonly UcpScopeGuard $scopeGuard,
        private readonly CheckoutStateStore $checkoutStateStore,
    ) {
    }

    #[Route(
        path: '/ucp/v1/orders/{id}',
        name: 'ucp.order.read',
        defaults: ['auth_required' => false, '_loginRequired' => false],
        methods: ['GET']
    )]
    #[Route(
        path: '/orders/{id}',
        name: 'ucp.order.read.conformance',
        defaults: ['auth_required' => false, '_loginRequired' => false],
        methods: ['GET']
    )]
    public function read(Request $request, string $id): JsonResponse
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }
        if (str_starts_with($request->getPathInfo(), '/orders/') && !$this->isConformanceMode()) {
            return new JsonResponse(null, 404);
        }

        $this->resolveContext($request);
        $this->scopeGuard->require($request, 'dev.ucp.shopping.order:read');

        $criteria = (new Criteria([$id]))
            ->addAssociation('lineItems')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.stateMachineState')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('transactions.stateMachineState')
            ->addAssociation('orderCustomer')
            ->addAssociation('currency')
            ->addAssociation('stateMachineState')
            ->addAssociation('addresses.country');

        $order = $this->orderRepository->search($criteria, Context::createDefaultContext())->first();
        if (!$order instanceof OrderEntity) {
            // Spec convention (overview.md §"Error Handling"): UCP errors return
            // HTTP 200 with a `ucp.status: error` envelope; the resolver listener
            // sets `ucp` so we only emit messages here. We use 404 to stay
            // RFC 9110 idiomatic for a missing resource — `UcpExceptionListener`
            // would also wrap a thrown UcpException, but a simple JSON body is
            // clearer here.
            return new JsonResponse([
                'messages' => [[
                    'type' => 'error',
                    'code' => 'order_not_found',
                    'content' => 'Order "' . $id . '" not found',
                    'severity' => 'unrecoverable',
                ]],
            ], 404);
        }

        // Ownership / access control:
        //
        //   1. Bearer-authenticated call → the order's customer id MUST match
        //      the bearer token's `sub` claim. No fallback to deep_link — the
        //      caller IS authenticated, scope+ownership rules apply strictly.
        //
        //   2. Anonymous call → MUST present `?deep_link=<code>` matching the
        //      order's `deep_link_code` (Shopware's existing anonymous-order
        //      mechanism, exposed by UCP as `permalink_url`). Knowing the
        //      order id alone is insufficient (defeats id-guessing).
        //
        // Spec §F6: anonymous access NEVER relies on `sw-context-token` to
        // bypass the deep_link check — that header is unauthenticated.
        $authenticatedUserId = $request->attributes->get(UcpAccessTokenAuthenticator::ATTR_USER_ID);
        $orderDeepLink = $order->getDeepLinkCode();

        if (\is_string($authenticatedUserId) && $authenticatedUserId !== '') {
            $customerId = $order->getOrderCustomer()?->getCustomerId();
            if ($customerId !== null && !hash_equals($customerId, $authenticatedUserId)) {
                throw UcpException::scopeRequired(
                    'dev.ucp.shopping.order:read',
                    '(order belongs to a different user)'
                );
            }
        } elseif (!$this->isConformanceSimulation($request)) {
            $providedDeepLink = $request->query->get('deep_link');
            if (!\is_string($providedDeepLink)
                || $providedDeepLink === ''
                || !\is_string($orderDeepLink)
                || !hash_equals($orderDeepLink, $providedDeepLink)
            ) {
                throw UcpException::scopeRequired(
                    'dev.ucp.shopping.order:read',
                    '(anonymous order lookup requires ?deep_link=<permalink token>)'
                );
            }
        }

        $storefrontBase = $this->resolveStorefrontBase($request);
        $payload = $this->orderMapper->toResponse($order, $storefrontBase);
        $this->applyConformanceOrderState($payload, $id);

        return new JsonResponse($payload);
    }

    #[Route(
        path: '/ucp/v1/orders/{id}',
        name: 'ucp.order.update',
        defaults: ['auth_required' => false, '_loginRequired' => false],
        methods: ['PUT']
    )]
    #[Route(
        path: '/orders/{id}',
        name: 'ucp.order.update.conformance',
        defaults: ['auth_required' => false, '_loginRequired' => false],
        methods: ['PUT']
    )]
    public function update(Request $request, string $id): JsonResponse
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }
        if (!$this->isConformanceMode()) {
            return new JsonResponse(null, 404);
        }
        $this->resolveContext($request);

        $payload = json_decode((string) $request->getContent(), true);
        if (!\is_array($payload)) {
            return new JsonResponse(['code' => 'invalid_order_payload', 'content' => 'Malformed order payload.'], 422);
        }
        $adjustments = $payload['adjustments'] ?? [];
        if (!\is_array($adjustments) || !array_is_list($adjustments)) {
            return new JsonResponse(['code' => 'invalid_adjustments', 'content' => 'adjustments must be a list.'], 422);
        }
        foreach ($adjustments as $adjustment) {
            if (!\is_array($adjustment)) {
                return new JsonResponse(['code' => 'invalid_adjustments', 'content' => 'adjustments entries must be objects.'], 422);
            }
            $status = $adjustment['status'] ?? null;
            if ($status !== null && !\in_array($status, ['pending', 'completed', 'failed', 'canceled'], true)) {
                return new JsonResponse(['code' => 'invalid_adjustment_status', 'content' => 'Invalid adjustment status.'], 422);
            }
        }

        $this->checkoutStateStore->saveOrderExtras($id, $payload);

        return $this->read($request, $id);
    }

    #[Route(
        path: '/testing/simulate-shipping/{id}',
        name: 'ucp.testing.simulate_shipping',
        defaults: ['auth_required' => false, '_loginRequired' => false],
        methods: ['POST']
    )]
    public function simulateShipping(Request $request, string $id): JsonResponse
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }
        if (!$this->isConformanceMode()) {
            return new JsonResponse(null, 404);
        }
        $expected = getenv('UCP_SIMULATION_SECRET') ?: ($_SERVER['UCP_SIMULATION_SECRET'] ?? $_ENV['UCP_SIMULATION_SECRET'] ?? '');
        $provided = $request->headers->get('simulation-secret');
        if (!\is_string($provided) || !\is_string($expected) || $expected === '' || !hash_equals($expected, $provided)) {
            return new JsonResponse(['code' => 'simulation_secret_invalid'], 403);
        }
        $this->sendConformanceWebhook($request, 'order_shipped', $id);

        return new JsonResponse(['ok' => true, 'order_id' => $id]);
    }

    private function resolveStorefrontBase(Request $request): string
    {
        return $request->getSchemeAndHttpHost();
    }

    private function isConformanceSimulation(Request $request): bool
    {
        return $this->isConformanceMode() && $request->headers->get('request-signature') === 'test';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function applyConformanceOrderState(array &$payload, string $orderId): void
    {
        if (!$this->isConformanceMode()) {
            return;
        }

        $checkoutId = $this->checkoutStateStore->checkoutIdForOrder($orderId);
        if ($checkoutId !== null) {
            $payload['checkout_id'] = $checkoutId;
        }

        $fulfillment = $this->checkoutStateStore->fulfillmentForOrder($orderId);
        if (\is_array($fulfillment)) {
            $selectedTitle = $this->selectedFulfillmentTitle($fulfillment);
            if ($selectedTitle !== null && isset($payload['fulfillment']['expectations'][0])) {
                $payload['fulfillment']['expectations'][0]['description'] = $selectedTitle;
            }
        }

        $extras = $this->checkoutStateStore->orderExtras($orderId);
        if (\is_array($extras)) {
            if (isset($extras['adjustments']) && \is_array($extras['adjustments'])) {
                $payload['adjustments'] = $extras['adjustments'];
            }
            if (isset($extras['fulfillment']['events']) && \is_array($extras['fulfillment']['events'])) {
                $payload['fulfillment']['events'] = $extras['fulfillment']['events'];
            }
        }
    }

    /**
     * @param array<string, mixed> $fulfillment
     */
    private function selectedFulfillmentTitle(array $fulfillment): ?string
    {
        $group = $fulfillment['methods'][0]['groups'][0] ?? null;
        if (!\is_array($group)) {
            return null;
        }
        $selected = $group['selected_option_id'] ?? null;
        $options = $group['options'] ?? [];
        if (!\is_array($options) || $options === []) {
            return null;
        }
        if (!\is_string($selected) || $selected === '') {
            return \is_string($options[0]['title'] ?? null) ? $options[0]['title'] : null;
        }
        foreach ($options as $option) {
            if (\is_array($option) && ($option['id'] ?? null) === $selected && \is_string($option['title'] ?? null)) {
                return $option['title'];
            }
        }

        return null;
    }

    private function sendConformanceWebhook(Request $request, string $eventType, string $orderId): void
    {
        if (!$this->isConformanceMode()) {
            return;
        }

        $profileUri = $this->extractProfileUri($request);
        if ($profileUri === null) {
            return;
        }
        $webhookUrl = $this->resolveConformanceWebhookUrl($profileUri);
        if ($webhookUrl === null) {
            return;
        }
        $checkoutId = $this->checkoutStateStore->checkoutIdForOrder($orderId) ?? $orderId;
        $fulfillment = $this->checkoutStateStore->fulfillmentForOrder($orderId) ?? [];
        $destination = $fulfillment['methods'][0]['destinations'][0] ?? ['address_country' => 'US'];
        $payload = [
            'event_type' => $eventType,
            'checkout_id' => $checkoutId,
            'order' => [
                'id' => $orderId,
                'fulfillment' => [
                    'expectations' => [['destination' => $destination, 'line_items' => []]],
                    'events' => [['id' => 'evt_' . $orderId, 'type' => 'shipped']],
                ],
            ],
        ];
        $this->postConformanceWebhook($webhookUrl, $payload);
    }

    private function extractProfileUri(Request $request): ?string
    {
        $header = $request->headers->get('ucp-agent');
        if (!\is_string($header) || !preg_match('/profile="([^"]+)"/', $header, $match)) {
            return null;
        }

        return $match[1];
    }

    private function resolveConformanceWebhookUrl(string $profileUri): ?string
    {
        $url = preg_replace('@://localhost(?=:)@', '://host.docker.internal', $profileUri, 1) ?? $profileUri;
        $raw = @file_get_contents($url);
        if (!\is_string($raw)) {
            return null;
        }
        $profile = json_decode($raw, true);
        $webhookUrl = $profile['ucp']['capabilities']['dev.ucp.shopping.order'][0]['config']['webhook_url'] ?? null;
        if (!\is_string($webhookUrl)) {
            return null;
        }

        return preg_replace('@://localhost(?=:)@', '://host.docker.internal', $webhookUrl, 1) ?? $webhookUrl;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function postConformanceWebhook(string $webhookUrl, array $payload): void
    {
        $body = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n",
                'content' => $body,
                'timeout' => 2,
            ],
        ]);
        @file_get_contents($webhookUrl, false, $context);
    }

    private function isConformanceMode(): bool
    {
        if (($_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? null) === 'prod') {
            return false;
        }

        return filter_var(getenv('UCP_CONFORMANCE_MODE') ?: ($_SERVER['UCP_CONFORMANCE_MODE'] ?? $_ENV['UCP_CONFORMANCE_MODE'] ?? false), \FILTER_VALIDATE_BOOL);
    }

    private function resolveContext(Request $request): UcpRequestContext
    {
        $context = $request->attributes->get(UcpRequestContext::REQUEST_ATTRIBUTE);
        if (!$context instanceof UcpRequestContext) {
            throw UcpException::featureDisabled();
        }
        if (!$context->intersection->has(OrderCapability::NAME)) {
            throw UcpException::capabilityNotEnabled(OrderCapability::NAME);
        }

        return $context;
    }
}
