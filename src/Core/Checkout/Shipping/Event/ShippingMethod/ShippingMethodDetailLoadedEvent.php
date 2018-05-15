<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Event\ShippingMethod;

use Shopware\Checkout\Shipping\Collection\ShippingMethodDetailCollection;
use Shopware\Checkout\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceBasicLoadedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethodTranslation\ShippingMethodTranslationBasicLoadedEvent;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShippingMethodDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ShippingMethodDetailCollection
     */
    protected $shippingMethods;

    public function __construct(ShippingMethodDetailCollection $shippingMethods, ApplicationContext $context)
    {
        $this->context = $context;
        $this->shippingMethods = $shippingMethods;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
