<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Cart;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartDeleteRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemRemoveRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemUpdateRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartLoadRoute;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Catalog\ProductIdentifierResolver;
use Shopware\Core\Framework\Ucp\Capability\IdentityLinking\Bridge\UcpScopeGuard;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Rest\UcpRouteScope;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * UCP Cart REST binding. All endpoints delegate to the existing Store-API
 * cart routes after mapping the UCP request payload to Shopware's domain
 * primitives, then re-map the resulting {@see Cart} via {@see CartMapper}.
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [UcpRouteScope::ID]])]
class CartController
{
    public function __construct(
        private readonly AbstractCartLoadRoute $cartLoadRoute,
        private readonly AbstractCartItemAddRoute $cartItemAddRoute,
        private readonly AbstractCartItemUpdateRoute $cartItemUpdateRoute,
        private readonly AbstractCartItemRemoveRoute $cartItemRemoveRoute,
        private readonly AbstractCartDeleteRoute $cartDeleteRoute,
        private readonly LineItemFactoryRegistry $lineItemFactory,
        private readonly PromotionItemBuilder $promotionItemBuilder,
        private readonly ProductIdentifierResolver $productIdentifierResolver,
        private readonly CartMapper $cartMapper,
        private readonly UcpScopeGuard $scopeGuard,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
    ) {
    }

    #[Route(path: '/ucp/v1/carts', name: 'ucp.cart.create', defaults: ['auth_required' => false, '_loginRequired' => false], methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $context = $this->resolveContext($request);
        // Scope is enforced only when a bearer token is present; anonymous
        // agent carts are allowed per spec.
        $this->scopeGuard->require($request, 'dev.ucp.shopping.cart:manage');
        $sc = $context->salesChannelContext;
        $payload = $this->decodeBody($request);
        $lineItems = $this->buildLineItems($payload['line_items'] ?? [], $sc);

        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();
        $response = $this->cartItemAddRoute->add($request, $loaded, $sc, $lineItems);
        $cart = $this->applyDiscountCodes($request, $response->getCart(), $sc, $payload);

        $sigVerified = (bool) $request->attributes->get(UcpRequestContext::ATTR_SIGNATURE_VERIFIED, false);

        return new JsonResponse(
            $this->cartMapper->toResponse($cart, $sc, null, $context, $payload, $sigVerified),
            201
        );
    }

    #[Route(path: '/ucp/v1/carts/{id}', name: 'ucp.cart.read', defaults: ['auth_required' => false, '_loginRequired' => false], methods: ['GET'])]
    public function read(Request $request, string $id): JsonResponse
    {
        $context = $this->resolveContext($request);
        $this->bindCartContextToken($request, $id);
        $loaded = $this->cartLoadRoute->load($request, $context->salesChannelContext)->getCart();

        $sigVerified = (bool) $request->attributes->get(UcpRequestContext::ATTR_SIGNATURE_VERIFIED, false);

        return new JsonResponse(
            $this->cartMapper->toResponse($loaded, $context->salesChannelContext, null, $context, [], $sigVerified)
        );
    }

    #[Route(path: '/ucp/v1/carts/{id}', name: 'ucp.cart.update', defaults: ['auth_required' => false, '_loginRequired' => false], methods: ['PUT', 'PATCH'])]
    public function update(Request $request, string $id): JsonResponse
    {
        $context = $this->resolveContext($request);
        $this->scopeGuard->require($request, 'dev.ucp.shopping.cart:manage');
        $sc = $context->salesChannelContext;
        $this->bindCartContextToken($request, $id);

        $payload = $this->decodeBody($request);
        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();

        $desired = $payload['line_items'] ?? [];
        $currentMap = [];
        foreach ($loaded->getLineItems() as $lineItem) {
            $currentMap[$lineItem->getId()] = $lineItem;
        }

        $toAdd = [];
        $toUpdate = [];
        $touched = [];

        foreach ($desired as $item) {
            $itemId = $item['id'] ?? null;
            if (\is_string($itemId) && isset($currentMap[$itemId])) {
                $touched[$itemId] = true;
                $toUpdate[] = ['id' => $itemId, 'quantity' => (int) ($item['quantity'] ?? $currentMap[$itemId]->getQuantity())];
            } else {
                $toAdd[] = $this->buildLineItem($item, $sc);
            }
        }

        $toRemoveIds = array_diff(array_keys($currentMap), array_keys($touched));

        if ($toUpdate !== []) {
            // CartItemUpdateRoute::change() reads the items from the request
            // body's `items[]` field — its second parameter is the actual
            // Cart, not the line-item list. Mutating $request here is fine
            // because the controller does not pass it on after this block.
            $request->request->set('items', $toUpdate);
            $this->cartItemUpdateRoute->change($request, $loaded, $sc);
        }
        if ($toAdd !== []) {
            $this->cartItemAddRoute->add($request, $loaded, $sc, $toAdd);
        }
        if ($toRemoveIds !== []) {
            $request->request->set('ids', array_values($toRemoveIds));
            $this->cartItemRemoveRoute->remove($request, $loaded, $sc);
        }

        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();
        $loaded = $this->applyDiscountCodes($request, $loaded, $sc, $payload);
        $sigVerified = (bool) $request->attributes->get(UcpRequestContext::ATTR_SIGNATURE_VERIFIED, false);

        return new JsonResponse(
            $this->cartMapper->toResponse($loaded, $sc, null, $context, $payload, $sigVerified)
        );
    }

    #[Route(path: '/ucp/v1/carts/{id}/cancel', name: 'ucp.cart.cancel', defaults: ['auth_required' => false, '_loginRequired' => false], methods: ['POST'])]
    public function cancel(Request $request, string $id): JsonResponse
    {
        $context = $this->resolveContext($request);
        $this->scopeGuard->require($request, 'dev.ucp.shopping.cart:manage');
        $this->bindCartContextToken($request, $id);
        // CartDeleteRoute uses the context's token — rebuild context to the cart's token.
        $sc = $this->rebindSalesChannelContext($context->salesChannelContext, $id);
        $this->cartDeleteRoute->delete($sc);

        return new JsonResponse([
            'id' => $id,
            'status' => 'canceled',
        ]);
    }

    /**
     * Bind the requested cart token to the request so `CartLoadRoute` can find
     * it. `CartLoadRoute::load()` calls `$request->get('token', $context->getToken())`
     * which inspects **query/body/attributes** — NOT headers — so setting only
     * `sw-context-token` header would load the wrong cart (the one bound to
     * the agent context the resolver created at the start of the request).
     */
    private function bindCartContextToken(Request $request, string $cartToken): void
    {
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $cartToken);
        $request->attributes->set('token', $cartToken);
        $request->query->set('token', $cartToken);
        $request->request->set('token', $cartToken);
    }

    /**
     * Recreate the SalesChannelContext using the given cart token so that any
     * Store-API route that reads `$context->getToken()` (e.g. CartDeleteRoute)
     * acts on the platform-supplied cart, not the resolver's synthetic token.
     */
    private function rebindSalesChannelContext(SalesChannelContext $original, string $cartToken): SalesChannelContext
    {
        return $this->salesChannelContextFactory->create(
            $cartToken,
            $original->getSalesChannelId(),
            array_filter([
                SalesChannelContextService::DOMAIN_ID => $original->getDomainId(),
            ])
        );
    }

    private function resolveContext(Request $request): UcpRequestContext
    {
        $context = $request->attributes->get(UcpRequestContext::REQUEST_ATTRIBUTE);
        if (!$context instanceof UcpRequestContext) {
            throw UcpException::featureDisabled();
        }
        if (!$context->intersection->has(CartCapability::NAME)) {
            throw UcpException::capabilityNotEnabled(CartCapability::NAME);
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
     * @param array<int, array<string, mixed>> $rawLineItems
     *
     * @return array<int, LineItem>
     */
    private function buildLineItems(array $rawLineItems, SalesChannelContext $context): array
    {
        $items = [];
        foreach ($rawLineItems as $raw) {
            $items[] = $this->buildLineItem($raw, $context);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function buildLineItem(array $raw, SalesChannelContext $context): LineItem
    {
        $referencedId = $raw['item']['id'] ?? $raw['referenced_id'] ?? null;
        $quantity = (int) ($raw['quantity'] ?? 1);
        if (!\is_string($referencedId) || $referencedId === '') {
            throw UcpException::featureDisabled();
        }
        $shopwareId = $this->productIdentifierResolver->resolveToShopwareId($referencedId, $context);
        if ($shopwareId === null) {
            throw UcpException::invalidArgument('Unknown product identifier: ' . $referencedId);
        }

        return $this->lineItemFactory->create([
            'type' => 'product',
            'referencedId' => $shopwareId,
            'quantity' => $quantity,
        ], $context);
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
