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
    private $calculator;

    public function __construct(DeliveryBuilder $builder, DeliveryCalculator $calculator)
    {
        $this->builder = $builder;
        $this->calculator = $calculator;
    }

    public function process(Cart $cart, CheckoutContext $context): DeliveryCollection
    {
        $deliveries = $cart->getDeliveries();

        $deliveries = $this->builder->build($deliveries, $cart->getLineItems(), $context);

        $this->calculator->calculate($cart->getDeliveries(), $cart, $context);

        return $deliveries;
    }
}
