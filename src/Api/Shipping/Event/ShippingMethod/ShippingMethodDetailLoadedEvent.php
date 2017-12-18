<?php declare(strict_types=1);

namespace Shopware\Api\Shipping\Event\ShippingMethod;

use Shopware\Api\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\Api\Order\Event\OrderDelivery\OrderDeliveryBasicLoadedEvent;
use Shopware\Api\Shipping\Collection\ShippingMethodDetailCollection;
use Shopware\Api\Shipping\Event\ShippingMethodPrice\ShippingMethodPriceBasicLoadedEvent;
use Shopware\Api\Shipping\Event\ShippingMethodTranslation\ShippingMethodTranslationBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShippingMethodDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shipping_method.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShippingMethodDetailCollection
     */
    protected $shippingMethods;

    public function __construct(ShippingMethodDetailCollection $shippingMethods, TranslationContext $context)
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

    public function getShippingMethods(): ShippingMethodDetailCollection
    {
        return $this->shippingMethods;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->shippingMethods->getCustomerGroups()->count() > 0) {
            $events[] = new CustomerGroupBasicLoadedEvent($this->shippingMethods->getCustomerGroups(), $this->context);
        }
        if ($this->shippingMethods->getOrderDeliveries()->count() > 0) {
            $events[] = new OrderDeliveryBasicLoadedEvent($this->shippingMethods->getOrderDeliveries(), $this->context);
        }
        if ($this->shippingMethods->getPrices()->count() > 0) {
            $events[] = new ShippingMethodPriceBasicLoadedEvent($this->shippingMethods->getPrices(), $this->context);
        }
        if ($this->shippingMethods->getTranslations()->count() > 0) {
            $events[] = new ShippingMethodTranslationBasicLoadedEvent($this->shippingMethods->getTranslations(), $this->context);
        }
        if ($this->shippingMethods->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->shippingMethods->getShops(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
