<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Event;

use Shopware\Framework\Context;
use Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Collection\ShippingMethodPriceDetailCollection;
use Shopware\Checkout\Shipping\Event\ShippingMethodBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShippingMethodPriceDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method_price.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var \Shopware\Checkout\Shipping\Aggregate\ShippingMethodPrice\Collection\ShippingMethodPriceDetailCollection
     */
    protected $shippingMethodPrices;

    public function __construct(ShippingMethodPriceDetailCollection $shippingMethodPrices, Context $context)
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

    public function getShippingMethodPrices(): ShippingMethodPriceDetailCollection
    {
        return $this->shippingMethodPrices;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->shippingMethodPrices->getShippingMethods()->count() > 0) {
            $events[] = new ShippingMethodBasicLoadedEvent($this->shippingMethodPrices->getShippingMethods(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
