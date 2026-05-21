<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Transport\Mcp\Tool;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartItemAddRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\AbstractCartLoadRoute;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Capability\Catalog\ProductIdentifierResolver;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutCapability;
use Shopware\Core\Framework\Ucp\Capability\Checkout\CheckoutMapper;
use Shopware\Core\Framework\Ucp\Event\UcpCheckoutResponseEvent;
use Shopware\Core\Framework\Ucp\Negotiation\UcpRequestContext;
use Shopware\Core\Framework\Ucp\Transport\Mcp\AbstractUcpMcpTool;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpTool;
use Shopware\Core\Framework\Ucp\UcpEvents;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 */
#[UcpMcpTool(name: 'create_checkout', capability: CheckoutCapability::NAME, description: 'Create a UCP checkout session')]
#[Package('framework')]
class CreateCheckoutTool extends AbstractUcpMcpTool
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCartLoadRoute $cartLoadRoute,
        private readonly AbstractCartItemAddRoute $cartItemAddRoute,
        private readonly LineItemFactoryRegistry $lineItemFactory,
        private readonly CheckoutMapper $checkoutMapper,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ProductIdentifierResolver $productIdentifierResolver,
    ) {
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cart_id' => ['type' => 'string', 'description' => 'Optional — convert an existing cart to checkout'],
                'line_items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['item', 'quantity'],
                        'properties' => [
                            'item' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]],
                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function outputSchema(): ?array
    {
        return $this->ucpSchemaRef('checkout.json', 'checkout_resp');
    }

    public function invoke(array $arguments, UcpRequestContext $context): array
    {
        $sc = $context->salesChannelContext;
        $cartId = \is_string($arguments['cart_id'] ?? null) ? $arguments['cart_id'] : null;

        $request = new Request();
        if ($cartId !== null && $cartId !== '') {
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $cartId);

            $request->attributes->set('token', $cartId);

            $request->query->set('token', $cartId);
        }

        $loaded = $this->cartLoadRoute->load($request, $sc)->getCart();

        $rawLineItems = $arguments['line_items'] ?? [];
        if (\is_array($rawLineItems) && $rawLineItems !== [] && $cartId === null) {
            $items = [];
            foreach ($rawLineItems as $raw) {
                if (\is_array($raw)) {
                    $items[] = $this->buildLineItem($raw, $sc);
                }
            }
            $loaded = $this->cartItemAddRoute->add($request, $loaded, $sc, $items)->getCart();
        }

        // Pass the negotiated UCP context so the mapper enriches the
        // response with `available_instruments`, fulfillment, attribution,
        // and the buyer-consent block — without it the MCP client receives
        // a stripped-down checkout shape that the agent cannot complete.
        $response = $this->checkoutMapper->toResponse(
            $loaded,
            $sc,
            $context->config,
            false,
            $context,
            $arguments
        );
        $responseEvent = new UcpCheckoutResponseEvent($response['id'] ?? $loaded->getToken(), $response, $sc, $context);
        $this->eventDispatcher->dispatch($responseEvent, UcpEvents::CHECKOUT_RESPONSE);

        return $responseEvent->getResponse();
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function buildLineItem(array $raw, SalesChannelContext $sc): LineItem
    {
        $referencedId = $raw['item']['id'] ?? $raw['referenced_id'] ?? null;
        $quantity = (int) ($raw['quantity'] ?? 1);
        if (!\is_string($referencedId) || $referencedId === '') {
            throw UcpException::mcpToolInvalidArguments('create_checkout', 'line_item.item.id required');
        }
        $shopwareId = $this->productIdentifierResolver->resolveToShopwareId($referencedId, $sc);
        if ($shopwareId === null) {
            throw UcpException::mcpToolInvalidArguments('create_checkout', 'unknown product identifier: ' . $referencedId);
        }

        return $this->lineItemFactory->create([
            'type' => 'product',
            'referencedId' => $shopwareId,
            'quantity' => $quantity,
        ], $sc);
    }
}
