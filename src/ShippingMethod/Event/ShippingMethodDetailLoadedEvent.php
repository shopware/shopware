<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Event;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\Category\Event\CategoryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Holiday\Event\HolidayBasicLoadedEvent;
use Shopware\PaymentMethod\Event\PaymentMethodBasicLoadedEvent;
use Shopware\ShippingMethod\Struct\ShippingMethodDetailCollection;
use Shopware\ShippingMethodPrice\Event\ShippingMethodPriceBasicLoadedEvent;

class ShippingMethodDetailLoadedEvent extends NestedEvent
{
    const NAME = 'shipping_method.detail.loaded';

    /**
     * @var ShippingMethodDetailCollection
     */
    protected $shippingMethods;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ShippingMethodDetailCollection $shippingMethods, TranslationContext $context)
    {
        $this->shippingMethods = $shippingMethods;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getShippingMethods(): ShippingMethodDetailCollection
    {
        return $this->shippingMethods;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [
            new ShippingMethodBasicLoadedEvent($this->shippingMethods, $this->context),
        ];

        if ($this->shippingMethods->getCategories()->count() > 0) {
            $events[] = new CategoryBasicLoadedEvent($this->shippingMethods->getCategories(), $this->context);
        }
        if ($this->shippingMethods->getCountries()->count() > 0) {
            $events[] = new AreaCountryBasicLoadedEvent($this->shippingMethods->getCountries(), $this->context);
        }
        if ($this->shippingMethods->getHolidays()->count() > 0) {
            $events[] = new HolidayBasicLoadedEvent($this->shippingMethods->getHolidays(), $this->context);
        }
        if ($this->shippingMethods->getPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->shippingMethods->getPaymentMethods(), $this->context);
        }
        if ($this->shippingMethods->getPrices()->count() > 0) {
            $events[] = new ShippingMethodPriceBasicLoadedEvent($this->shippingMethods->getPrices(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
