<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Event;

use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\Event\ShippingMethodPriceBasicLoadedEvent;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\Event\ShippingMethodTranslationBasicLoadedEvent;
use Shopware\Core\Checkout\Shipping\Collection\ShippingMethodDetailCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;

class ShippingMethodDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ShippingMethodDetailCollection
     */
    protected $shippingMethods;

    public function __construct(ShippingMethodDetailCollection $shippingMethods, Context $context)
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

    public function getShippingMethods(): ShippingMethodDetailCollection
    {
        return $this->shippingMethods;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->shippingMethods->getPrices()->count() > 0) {
            $events[] = new ShippingMethodPriceBasicLoadedEvent($this->shippingMethods->getPrices(), $this->context);
        }
        if ($this->shippingMethods->getTranslations()->count() > 0) {
            $events[] = new ShippingMethodTranslationBasicLoadedEvent($this->shippingMethods->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
