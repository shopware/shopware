<?php declare(strict_types=1);

namespace Shopware\ShippingMethodPrice\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceBasicCollection;

class ShippingMethodPriceBasicLoadedEvent extends NestedEvent
{
    const NAME = 'shipping_method_price.basic.loaded';

    /**
     * @var ShippingMethodPriceBasicCollection
     */
    protected $shippingMethodPrices;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ShippingMethodPriceBasicCollection $shippingMethodPrices, TranslationContext $context)
    {
        $this->shippingMethodPrices = $shippingMethodPrices;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getShippingMethodPrices(): ShippingMethodPriceBasicCollection
    {
        return $this->shippingMethodPrices;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];

        return new NestedEventCollection($events);
    }
}
