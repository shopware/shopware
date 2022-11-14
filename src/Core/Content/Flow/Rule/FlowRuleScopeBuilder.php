<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Rule;

use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
class FlowRuleScopeBuilder implements ResetInterface
{
    private OrderConverter $orderConverter;

    private DeliveryBuilder $deliveryBuilder;

    /**
     * @var iterable<CartDataCollectorInterface>
     */
    private iterable $collectors;

    /**
     * @var array<string, FlowRuleScope>
     */
    private array $scopes = [];

    /**
     * @param iterable<CartDataCollectorInterface> $collectors
     */
    public function __construct(OrderConverter $orderConverter, DeliveryBuilder $deliveryBuilder, iterable $collectors)
    {
        $this->orderConverter = $orderConverter;
        $this->deliveryBuilder = $deliveryBuilder;
        $this->collectors = $collectors;
    }

    public function reset(): void
    {
        $this->scopes = [];
    }

    public function build(OrderEntity $order, Context $context): FlowRuleScope
    {
        if (\array_key_exists($order->getId(), $this->scopes)) {
            return $this->scopes[$order->getId()];
        }

        $context = $this->orderConverter->assembleSalesChannelContext($order, $context);
        $cart = $this->orderConverter->convertToCart($order, $context->getContext());
        $behavior = new CartBehavior($context->getPermissions());

        foreach ($this->collectors as $collector) {
            $collector->collect($cart->getData(), $cart, $context, $behavior);
        }

        $cart->setDeliveries(
            $this->deliveryBuilder->build($cart, $cart->getData(), $context, $behavior)
        );

        return $this->scopes[$order->getId()] = new FlowRuleScope($order, $cart, $context);
    }
}
