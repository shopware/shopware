<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp\Tool;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemUpdateRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartLoadRoute;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartCapability;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartMapper;
use Shopware\Core\Framework\Ucp\Capability\Catalog\ProductIdentifierResolver;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Mcp\AbstractUcpMcpTool;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpTool;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[UcpMcpTool(name: 'update_cart', capability: CartCapability::NAME, description: 'Update an existing cart\'s line items')]
#[Package('framework')]
class UpdateCartTool extends AbstractUcpMcpTool
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartLoadRoute $cartLoadRoute,
        private readonly AbstractCartItemAddRoute $cartItemAddRoute,
        private readonly AbstractCartItemUpdateRoute $cartItemUpdateRoute,
        private readonly LineItemFactoryRegistry $lineItemFactory,
        private readonly PromotionItemBuilder $promotionItemBuilder,
        private readonly ProductIdentifierResolver $productIdentifierResolver,
        private readonly CartMapper $cartMapper,
    ) {
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['id'],
            'properties' => [
                'id' => ['type' => 'string', 'description' => 'Cart id'],
                'line_items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string', 'description' => 'Existing line-item id (omit for new items)'],
                            'item' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                        ],
                    ],
                ],
                'discounts' => [
                    'type' => 'object',
                    'properties' => [
                        'codes' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                ],
            ],
        ];
    }

    public function outputSchema(): ?array
    {
        return $this->ucpSchemaRef('cart.json', 'cart_resp');
    }

    public function invoke(array $arguments, UcpRequestContext $context): array
    {
        $sc = $context->salesChannelContext;
        $id = \is_string($arguments['id'] ?? null) ? $arguments['id'] : '';
        if ($id === '') {
            throw UcpException::mcpToolInvalidArguments('update_cart', 'cart id required');
        }

        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $id);

        $request->attributes->set('token', $id);

        $request->query->set('token', $id);

        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();
        $desired = $arguments['line_items'] ?? [];

        $toUpdate = [];
        $toAdd = [];

        foreach ($desired as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $lineId = $entry['id'] ?? null;
            if (\is_string($lineId) && $lineId !== '' && $loaded->has($lineId)) {
                $toUpdate[] = ['id' => $lineId, 'quantity' => (int) ($entry['quantity'] ?? 1)];
            } else {
                $toAdd[] = $this->buildLineItem($entry, $sc);
            }
        }

        if ($toUpdate !== []) {
            $request->request->set('items', $toUpdate);
            $loaded = $this->cartItemUpdateRoute->change($request, $loaded, $sc)->getCart();
        }
        if ($toAdd !== []) {
            $loaded = $this->cartItemAddRoute->add($request, $loaded, $sc, $toAdd)->getCart();
        }
        $codes = $arguments['discounts']['codes'] ?? null;
        if (\is_array($codes)) {
            foreach ($codes as $code) {
                if (!\is_string($code) || trim($code) === '') {
                    continue;
                }
                $loaded = $this->cartItemAddRoute->add(
                    $request,
                    $loaded,
                    $sc,
                    [$this->promotionItemBuilder->buildPlaceholderItem(trim($code))]
                )->getCart();
            }
        }

        $cart = $this->cartLoadRoute->load($request, $sc)->getCart();

        return $this->cartMapper->toResponse($cart, $sc, null, $context, $arguments);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function buildLineItem(array $raw, SalesChannelContext $sc): LineItem
    {
        $referencedId = $raw['item']['id'] ?? $raw['referenced_id'] ?? null;
        $quantity = (int) ($raw['quantity'] ?? 1);
        if (!\is_string($referencedId) || $referencedId === '') {
            throw UcpException::mcpToolInvalidArguments('update_cart', 'line_item.item.id required');
        }
        $shopwareId = $this->productIdentifierResolver->resolveToShopwareId($referencedId, $sc);
        if ($shopwareId === null) {
            throw UcpException::mcpToolInvalidArguments('update_cart', 'unknown product identifier: ' . $referencedId);
        }

        return $this->lineItemFactory->create([
            'type' => 'product',
            'referencedId' => $shopwareId,
            'quantity' => $quantity,
        ], $sc);
    }
}
