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
    const NAME = 'shippingMethod.detail.loaded';

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
        return new NestedEventCollection([
            new ShippingMethodBasicLoadedEvent($this->shippingMethods, $this->context),
            new CategoryBasicLoadedEvent($this->shippingMethods->getCategories(), $this->context),
            new AreaCountryBasicLoadedEvent($this->shippingMethods->getCountries(), $this->context),
            new HolidayBasicLoadedEvent($this->shippingMethods->getHolidaies(), $this->context),
            new PaymentMethodBasicLoadedEvent($this->shippingMethods->getPaymentMethods(), $this->context),
            new ShippingMethodPriceBasicLoadedEvent($this->shippingMethods->getPrices(), $this->context),
        ]);
    }
}
