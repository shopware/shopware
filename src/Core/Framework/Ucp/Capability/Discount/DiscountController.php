<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Capability\Discount;

use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartLoadRoute;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartCapability;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartMapper;
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
 * REST surface for applying / removing discount codes on a UCP cart per
 * `ucp/docs/specification/discount.md`.
 *
 *   POST   /ucp/v1/carts/{id}/discounts   { code: "WELCOME10" }
 *   DELETE /ucp/v1/carts/{id}/discounts/{lineItemId}
 *
 * The response shape is identical to `GET /ucp/v1/carts/{id}` — the buyer
 * always gets a full cart view back so they can see how their totals
 * changed.
 *
 * Rejected codes are surfaced via the `messages[]` array with severity
 * `requires_buyer_input` (see {@see DiscountMapper::extractRejectedCodes()}).
 *
 * @internal
 */
#[Package('framework')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [UcpRouteScope::ID]])]
class DiscountController
{
    public function __construct(
        private readonly AbstractCartLoadRoute $cartLoadRoute,
        private readonly AbstractCartItemAddRoute $cartItemAddRoute,
        private readonly PromotionItemBuilder $promotionItemBuilder,
        private readonly CartMapper $cartMapper,
        private readonly UcpScopeGuard $scopeGuard,
    ) {
    }

    #[Route(
        path: '/ucp/v1/carts/{id}/discounts',
        name: 'ucp.cart.discount.apply',
        defaults: ['auth_required' => false, '_loginRequired' => false],
        methods: ['POST']
    )]
    public function apply(Request $request, string $id): JsonResponse
    {
        if (!Feature::isActive('UCP_SERVER')) {
            throw UcpException::featureDisabled();
        }

        $context = $this->resolveContext($request, requiresDiscount: true);
        $this->scopeGuard->require($request, 'dev.ucp.shopping.cart:manage');

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $id);
        $request->attributes->set('token', $id);
        $request->query->set('token', $id);

        $payload = $this->decodeBody($request);
        $code = $payload['code'] ?? null;
        if (!\is_string($code) || trim($code) === '') {
            throw UcpException::invalidProfileUrl('(missing or empty `code` in body)');
        }

        $sc = $context->salesChannelContext;
        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();

        // Build a promotion line item from the code and let Shopware's cart
        // processor validate it — invalid codes surface as cart errors which
        // DiscountMapper picks up and emits as `messages[]`.
        $promotion = $this->promotionItemBuilder->buildPlaceholderItem(trim($code));
        $loaded = $this->cartItemAddRoute->add($request, $loaded, $sc, [$promotion])->getCart();

        $response = $this->cartMapper->toResponse(
            $loaded,
            $sc,
            null,
            $context,
            $payload
        );

        return new JsonResponse($response);
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

    private function resolveContext(Request $request, bool $requiresDiscount): UcpRequestContext
    {
        $context = $request->attributes->get(UcpRequestContext::REQUEST_ATTRIBUTE);
        if (!$context instanceof UcpRequestContext) {
            throw UcpException::featureDisabled();
        }
        if (!$context->intersection->has(CartCapability::NAME)) {
            throw UcpException::capabilityNotEnabled(CartCapability::NAME);
        }
        if ($requiresDiscount && !$context->intersection->has('dev.ucp.shopping.discount')) {
            throw UcpException::capabilityNotEnabled('dev.ucp.shopping.discount');
        }

        return $context;
    }
}
