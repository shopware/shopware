<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Event;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\Event\ShippingMethodPriceBasicLoadedEvent;
use Shopware\Core\Checkout\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class ShippingMethodBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var ShippingMethodBasicCollection
     */
    protected $shippingMethods;

    public function __construct(ShippingMethodBasicCollection $shippingMethods, Context $context)
    {
        $this->context = $context;
        $this->shippingMethods = $shippingMethods;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return $this->shippingMethods;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->shippingMethods->getPrices()->count() > 0) {
            $events[] = new ShippingMethodPriceBasicLoadedEvent($this->shippingMethods->getPrices(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
