<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-cart-manage', description: 'Manage a storefront shopping cart. Actions: "create" (new cart, returns token), "add" (add product by productId), "remove" (remove by lineItemId), "update" (change quantity by lineItemId), "get" (view current cart). Requires salesChannelId (see shopware://sales-channels resource). All actions except "create" require the token returned by "create". Optionally pass customerId for customer-specific pricing.')]
#[Package('framework')]
class CartManageTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly CartService $cartService,
    ) {
    }

    public function __invoke(
        string $salesChannelId,
        string $action,
        string $token = '',
        string $productId = '',
        int $quantity = 1,
        string $lineItemId = '',
        ?string $customerId = null,
    ): string {
        $validActions = ['create', 'add', 'remove', 'update', 'get'];
        if (!\in_array($action, $validActions, true)) {
            return $this->error('Invalid action "' . $action . '". Must be one of: ' . implode(', ', $validActions));
        }

        if ($action !== 'create' && $token === '') {
            return $this->error('Token is required for action "' . $action . '". Use action "create" first to get a token.');
        }

        try {
            return match ($action) {
                'create' => $this->handleCreate($salesChannelId, $customerId),
                'add' => $this->handleAdd($salesChannelId, $token, $productId, $quantity, $customerId),
                'remove' => $this->handleRemove($salesChannelId, $token, $lineItemId, $customerId),
                'update' => $this->handleUpdate($salesChannelId, $token, $lineItemId, $quantity, $customerId),
                'get' => $this->handleGet($salesChannelId, $token, $customerId),
            };
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    private function handleCreate(string $salesChannelId, ?string $customerId): string
    {
        $token = Random::getAlphanumericString(32);

        $this->contextService->get(new SalesChannelContextServiceParameters(
            salesChannelId: $salesChannelId,
            token: $token,
            customerId: $customerId,
        ));

        $this->cartService->createNew($token);

        return $this->success([
            'token' => $token,
            'lineItems' => [],
            'totalPrice' => 0.0,
            'itemCount' => 0,
        ]);
    }

    private function handleAdd(string $salesChannelId, string $token, string $productId, int $quantity, ?string $customerId): string
    {
        if ($productId === '') {
            return $this->error('productId is required for action "add".');
        }

        $context = $this->contextService->get(new SalesChannelContextServiceParameters(
            salesChannelId: $salesChannelId,
            token: $token,
            customerId: $customerId,
        ));

        $cart = $this->cartService->getCart($token, $context);
        $lineItem = new LineItem($productId, LineItem::PRODUCT_LINE_ITEM_TYPE, $productId, $quantity);
        $cart = $this->cartService->add($cart, $lineItem, $context);

        return $this->formatCart($cart, $token);
    }

    private function handleRemove(string $salesChannelId, string $token, string $lineItemId, ?string $customerId): string
    {
        if ($lineItemId === '') {
            return $this->error('lineItemId is required for action "remove".');
        }

        $context = $this->contextService->get(new SalesChannelContextServiceParameters(
            salesChannelId: $salesChannelId,
            token: $token,
            customerId: $customerId,
        ));

        $cart = $this->cartService->getCart($token, $context);
        $cart = $this->cartService->remove($cart, $lineItemId, $context);

        return $this->formatCart($cart, $token);
    }

    private function handleUpdate(string $salesChannelId, string $token, string $lineItemId, int $quantity, ?string $customerId): string
    {
        if ($lineItemId === '') {
            return $this->error('lineItemId is required for action "update".');
        }

        $context = $this->contextService->get(new SalesChannelContextServiceParameters(
            salesChannelId: $salesChannelId,
            token: $token,
            customerId: $customerId,
        ));

        $cart = $this->cartService->getCart($token, $context);
        $cart = $this->cartService->changeQuantity($cart, $lineItemId, $quantity, $context);

        return $this->formatCart($cart, $token);
    }

    private function handleGet(string $salesChannelId, string $token, ?string $customerId): string
    {
        $context = $this->contextService->get(new SalesChannelContextServiceParameters(
            salesChannelId: $salesChannelId,
            token: $token,
            customerId: $customerId,
        ));

        $cart = $this->cartService->getCart($token, $context);

        return $this->formatCart($cart, $token);
    }

    private function formatCart(Cart $cart, string $token): string
    {
        $lineItems = [];
        foreach ($cart->getLineItems()->getElements() as $item) {
            $lineItems[] = [
                'id' => $item->getId(),
                'type' => $item->getType(),
                'label' => $item->getLabel(),
                'quantity' => $item->getQuantity(),
                'unitPrice' => $item->getPrice()?->getUnitPrice(),
                'totalPrice' => $item->getPrice()?->getTotalPrice(),
                'productId' => $item->getReferencedId(),
            ];
        }

        return $this->success([
            'token' => $token,
            'lineItems' => $lineItems,
            'totalPrice' => $cart->getPrice()->getTotalPrice(),
            'netPrice' => $cart->getPrice()->getNetPrice(),
            'taxAmount' => $cart->getPrice()->getCalculatedTaxes()->getAmount(),
            'itemCount' => $cart->getLineItems()->count(),
        ]);
    }
}
