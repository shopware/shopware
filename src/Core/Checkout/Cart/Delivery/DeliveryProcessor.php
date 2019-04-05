<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DeliveryProcessor
{
    /**
     * @var DeliveryBuilder
     */
    protected $builder;

    /**
     * @var DeliveryCalculator
     */
    protected $deliveryCalculator;

    public function __construct(DeliveryBuilder $builder, DeliveryCalculator $deliveryCalculator)
    {
        $this->builder = $builder;
        $this->deliveryCalculator = $deliveryCalculator;
    }

    public function process(
        Cart $cart,
        LineItemCollection $lineItems,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): DeliveryCollection {
        if ($behavior->isRecalculation()) {
            $deliveries = $cart->getDeliveries();

            $this->deliveryCalculator->calculate($deliveries, $context);

            return $deliveries;
        }

        $deliveries = $this->builder->build(new DeliveryCollection(), $lineItems, $context, false);

        $this->deliveryCalculator->calculate($deliveries, $context);

        return $deliveries;
    }
}
