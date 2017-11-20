<?php declare(strict_types=1);

namespace Shopware\Shipping\Event\ShippingMethod;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceBasicLoadedEvent;

class ShippingMethodBasicLoadedEvent extends NestedEvent
{
    const NAME = 'shipping_method.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShippingMethodBasicCollection
     */
    protected $shippingMethods;

    public function __construct(ShippingMethodBasicCollection $shippingMethods, TranslationContext $context)
    {
        $this->context = $context;
        $this->shippingMethods = $shippingMethods;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
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
