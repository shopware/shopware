<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpTool(name: 'shopware-cart-checkout', description: 'Place an order from an existing cart. Requires a cart token (from shopware-cart-manage "create"), a salesChannelId, and a customerId (registered customer). Optionally override the payment and shipping method (use shopware-checkout-methods to list available methods). Defaults to dryRun=true for order preview.')]
#[Package('framework')]
class CartCheckoutTool
{
    use McpToolResponse;

    /**
     * @internal
     */
    public function __construct(
        private readonly SalesChannelContextServiceInterface $contextService,
        private readonly CartService $cartService,
        private readonly McpContextProvider $contextProvider,
    ) {
    }

    public function __invoke(
        string $salesChannelId,
        string $token,
        string $customerId,
        string $paymentMethodId = '',
        string $shippingMethodId = '',
        bool $dryRun = true,
    ): string {
        $context = $this->contextProvider->getContext();

        if ($error = $this->requirePrivilege($context, 'sales_channel:read')) {
            return $error;
        }

        try {
            $context = $this->contextService->get(new SalesChannelContextServiceParameters(
                salesChannelId: $salesChannelId,
                token: $token,
                customerId: $customerId,
            ));

            $cart = $this->cartService->getCart($token, $context);

            if ($cart->getLineItems()->count() === 0) {
                return $this->error('Cart is empty. Add items with shopware-cart-manage first.');
            }

            $lineItems = [];
            foreach ($cart->getLineItems()->getElements() as $item) {
                $lineItems[] = [
                    'id' => $item->getId(),
                    'label' => $item->getLabel(),
                    'quantity' => $item->getQuantity(),
                    'totalPrice' => $item->getPrice()?->getTotalPrice(),
                ];
            }

            if ($dryRun) {
                return $this->success([
                    'token' => $token,
                    'customerId' => $customerId,
                    'lineItems' => $lineItems,
                    'totalPrice' => $cart->getPrice()->getTotalPrice(),
                    'netPrice' => $cart->getPrice()->getNetPrice(),
                    'taxAmount' => $cart->getPrice()->getCalculatedTaxes()->getAmount(),
                    'paymentMethodId' => $paymentMethodId !== '' ? $paymentMethodId : $context->getPaymentMethod()->getId(),
                    'shippingMethodId' => $shippingMethodId !== '' ? $shippingMethodId : $context->getShippingMethod()->getId(),
                ], ['dryRun' => true]);
            }

            $data = [];
            if ($paymentMethodId !== '') {
                $data['paymentMethodId'] = $paymentMethodId;
            }
            if ($shippingMethodId !== '') {
                $data['shippingMethodId'] = $shippingMethodId;
            }

            $orderId = $this->cartService->order($cart, $context, new RequestDataBag($data));

            return $this->success([
                'orderId' => $orderId,
                'totalPrice' => $cart->getPrice()->getTotalPrice(),
                'itemCount' => \count($lineItems),
            ], ['dryRun' => false]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}
