<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp\Tool;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartLoadRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartCapability;
use Shopware\Core\Framework\Ucp\Capability\Cart\CartMapper;
use Shopware\Core\Framework\Ucp\Capability\Catalog\ProductIdentifierResolver;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Mcp\AbstractUcpMcpTool;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpTool;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[UcpMcpTool(name: 'create_cart', capability: CartCapability::NAME, description: 'Create a UCP cart session')]
#[Package('framework')]
class CreateCartTool extends AbstractUcpMcpTool
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartLoadRoute $cartLoadRoute,
        private readonly AbstractCartItemAddRoute $cartItemAddRoute,
        private readonly LineItemFactoryRegistry $lineItemFactory,
        private readonly ProductIdentifierResolver $productIdentifierResolver,
        private readonly CartMapper $cartMapper,
    ) {
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['line_items'],
            'properties' => [
                'line_items' => [
                    'type' => 'array',
                    'minItems' => 1,
                    'items' => [
                        'type' => 'object',
                        'required' => ['item', 'quantity'],
                        'properties' => [
                            'item' => [
                                'type' => 'object',
                                'properties' => ['id' => ['type' => 'string']],
                            ],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                        ],
                    ],
                ],
                'context' => [
                    'type' => 'object',
                    'properties' => [
                        'address_country' => ['type' => 'string'],
                        'address_region' => ['type' => 'string'],
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
        $rawLineItems = $arguments['line_items'] ?? [];

        $request = new Request();
        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();

        $items = [];
        foreach ($rawLineItems as $raw) {
            if (\is_array($raw)) {
                $items[] = $this->buildLineItem($raw, $sc);
            }
        }
        $response = $this->cartItemAddRoute->add($request, $loaded, $sc, $items);

        return $this->cartMapper->toResponse($response->getCart(), $sc);
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function buildLineItem(array $raw, SalesChannelContext $sc): LineItem
    {
        $referencedId = $raw['item']['id'] ?? $raw['referenced_id'] ?? null;
        $quantity = (int) ($raw['quantity'] ?? 1);
        if (!\is_string($referencedId) || $referencedId === '') {
            throw UcpException::mcpToolInvalidArguments('create_cart', 'line_item.item.id required');
        }
        $shopwareId = $this->productIdentifierResolver->resolveToShopwareId($referencedId, $sc);
        if ($shopwareId === null) {
            throw UcpException::mcpToolInvalidArguments('create_cart', 'unknown product identifier: ' . $referencedId);
        }

        return $this->lineItemFactory->create([
            'type' => 'product',
            'referencedId' => $shopwareId,
            'quantity' => $quantity,
        ], $sc);
    }
}
