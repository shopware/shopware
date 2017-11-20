<?php declare(strict_types=1);

namespace Shopware\Shipping\Event\ShippingMethodPrice;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Shipping\Collection\ShippingMethodPriceDetailCollection;
use Shopware\Shipping\Event\ShippingMethod\ShippingMethodBasicLoadedEvent;

class ShippingMethodPriceDetailLoadedEvent extends NestedEvent
{
    const NAME = 'shipping_method_price.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShippingMethodPriceDetailCollection
     */
    protected $shippingMethodPrices;

    public function __construct(ShippingMethodPriceDetailCollection $shippingMethodPrices, TranslationContext $context)
    {
        $this->context = $context;
        $this->shippingMethodPrices = $shippingMethodPrices;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
