<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\Collection\ShippingMethodPriceBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;

class ShippingMethodPriceBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method_price.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\Collection\ShippingMethodPriceBasicCollection
     */
    protected $shippingMethodPrices;

    public function __construct(ShippingMethodPriceBasicCollection $shippingMethodPrices, Context $context)
    {
        $this->context = $context;
        $this->shippingMethodPrices = $shippingMethodPrices;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getShippingMethodPrices(): ShippingMethodPriceBasicCollection
    {
        return $this->shippingMethodPrices;
    }
}
