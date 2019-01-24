<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Delivery;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\CheckoutContext;

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
        CheckoutContext $context,
        bool $refresh = false
    ): DeliveryCollection {
        $deliveries = $cart->getDeliveries();

        if ($refresh === false) {
            $deliveries = $this->builder->build($deliveries, $lineItems, $context, false);
        }

        $this->deliveryCalculator->calculate($deliveries, $context);

        return $deliveries;
    }
}
