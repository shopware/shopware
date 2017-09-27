<?php declare(strict_types=1);

namespace Shopware\Shop\Event;

use Shopware\AreaCountry\Event\AreaCountryBasicLoadedEvent;
use Shopware\Category\Event\CategoryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Event\CurrencyBasicLoadedEvent;
use Shopware\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Locale\Event\LocaleBasicLoadedEvent;
use Shopware\PaymentMethod\Event\PaymentMethodBasicLoadedEvent;
use Shopware\ShippingMethod\Event\ShippingMethodBasicLoadedEvent;
use Shopware\Shop\Struct\ShopDetailCollection;
use Shopware\ShopTemplate\Event\ShopTemplateBasicLoadedEvent;

class ShopDetailLoadedEvent extends NestedEvent
{
    const NAME = 'shop.detail.loaded';

    /**
     * @var ShopDetailCollection
     */
    protected $shops;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ShopDetailCollection $shops, TranslationContext $context)
    {
        $this->shops = $shops;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getShops(): ShopDetailCollection
    {
        return $this->shops;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [
            new ShopBasicLoadedEvent($this->shops, $this->context),
        ];

        if ($this->shops->getFallbackLocales()->count() > 0) {
            $events[] = new LocaleBasicLoadedEvent($this->shops->getFallbackLocales(), $this->context);
        }
        if ($this->shops->getCategories()->count() > 0) {
            $events[] = new CategoryBasicLoadedEvent($this->shops->getCategories(), $this->context);
        }
        if ($this->shops->getCustomerGroups()->count() > 0) {
            $events[] = new CustomerGroupBasicLoadedEvent($this->shops->getCustomerGroups(), $this->context);
        }
        if ($this->shops->getPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->shops->getPaymentMethods(), $this->context);
        }
        if ($this->shops->getShippingMethods()->count() > 0) {
            $events[] = new ShippingMethodBasicLoadedEvent($this->shops->getShippingMethods(), $this->context);
        }
        if ($this->shops->getCountries()->count() > 0) {
            $events[] = new AreaCountryBasicLoadedEvent($this->shops->getCountries(), $this->context);
        }
        if ($this->shops->getTemplates()->count() > 0) {
            $events[] = new ShopTemplateBasicLoadedEvent($this->shops->getTemplates(), $this->context);
        }
        if ($this->shops->getAvailableCurrencies()->count() > 0) {
            $events[] = new CurrencyBasicLoadedEvent($this->shops->getAvailableCurrencies(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
