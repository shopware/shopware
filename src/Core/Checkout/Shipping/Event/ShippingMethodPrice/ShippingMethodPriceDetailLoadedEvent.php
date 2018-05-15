<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Event\ShippingMethodPrice;

use Shopware\Checkout\Shipping\Collection\ShippingMethodPriceDetailCollection;
use Shopware\Checkout\Shipping\Event\ShippingMethod\ShippingMethodBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShippingMethodPriceDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method_price.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ShippingMethodPriceDetailCollection
     */
    protected $shippingMethodPrices;

    public function __construct(ShippingMethodPriceDetailCollection $shippingMethodPrices, ApplicationContext $context)
    {
        $this->context = $context;
        $this->shippingMethodPrices = $shippingMethodPrices;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
