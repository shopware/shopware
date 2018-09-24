<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\CheckoutContext;

class DeliveryProcessor
{
    /**
     * @var DeliveryBuilder
     */
    private $builder;

    /**
     * @var DeliveryCalculator
     */
    private $deliveryCalculator;

    public function __construct(DeliveryBuilder $builder, DeliveryCalculator $deliveryCalculator)
    {
        $this->builder = $builder;
        $this->deliveryCalculator = $deliveryCalculator;
    }

    public function process(Cart $cart, CheckoutContext $context): DeliveryCollection
    {
        $deliveries = $cart->getDeliveries();

        $deliveries = $this->builder->build($deliveries, $cart->getLineItems(), $context);

        $this->deliveryCalculator->calculate($cart->getDeliveries(), $context);

        return $deliveries;
    }
}
