<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;

/**
 * @final
 */
#[Package('checkout')]
class OrderRestorer
{
    public const PERMISSIONS = [
        CheckoutPermissions::SKIP_PRODUCT_RECALCULATION => true,
        CheckoutPermissions::SKIP_DELIVERY_PRICE_RECALCULATION => true,
        CheckoutPermissions::SKIP_PRODUCT_STOCK_VALIDATION => true,
        CheckoutPermissions::KEEP_INACTIVE_PRODUCT => true,
        CheckoutPermissions::PIN_MANUAL_PROMOTIONS => true,
        CheckoutPermissions::PIN_AUTOMATIC_PROMOTIONS => true,
        CheckoutPermissions::SKIP_CART_PERSISTENCE => true,
    ];

    /**
     * @internal
     */
    public function __construct(
        private readonly OrderConverter $orderConverter,
        private readonly CartService $cartService,
    ) {
    }

    public function addRequiredAssociations(Criteria $criteria): Criteria
    {
        $criteria->addAssociations([
            'lineItems',
            'orderCustomer',
            'primaryOrderDelivery',
            'transactions.stateMachineState',
            'deliveries.shippingMethod',
            'deliveries.positions.orderLineItem',
            'deliveries.shippingOrderAddress.country',
            'deliveries.shippingOrderAddress.countryState',
        ]);

        $transactions = $criteria->getAssociation('transactions');
        if ($transactions->getSorting() === []) {
            $transactions->addSorting(new FieldSorting('createdAt'));
        }

        return $criteria;
    }

    /**
     * @param array<string, array<string, bool>|string|null> $overrideOptions
     */
    public function restore(OrderEntity $order, Context $context, array $overrideOptions = []): RestoredOrder
    {
        try {
            $restoredContext = $this->orderConverter->assembleSalesChannelContext(
                $order,
                $context,
                [SalesChannelContextService::PERMISSIONS => self::PERMISSIONS, ...$overrideOptions],
            );

            $cart = $this->orderConverter->convertToCart($order, $context);
            $cart->setToken($restoredContext->getToken());
        } catch (OrderException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw OrderException::orderRestorationFailed($order->getId(), $e);
        }

        $this->cartService->setCart($cart);

        return new RestoredOrder($order, $restoredContext, $cart);
    }
}
