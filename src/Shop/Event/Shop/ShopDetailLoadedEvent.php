<?php declare(strict_types=1);

namespace Shopware\Shop\Event\Shop;

use Shopware\Category\Event\Category\CategoryBasicLoadedEvent;
use Shopware\Category\Event\CategoryTranslation\CategoryTranslationBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\Country\Event\CountryAreaTranslation\CountryAreaTranslationBasicLoadedEvent;
use Shopware\Country\Event\CountryStateTranslation\CountryStateTranslationBasicLoadedEvent;
use Shopware\Country\Event\CountryTranslation\CountryTranslationBasicLoadedEvent;
use Shopware\Currency\Event\Currency\CurrencyBasicLoadedEvent;
use Shopware\Currency\Event\CurrencyTranslation\CurrencyTranslationBasicLoadedEvent;
use Shopware\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\Customer\Event\CustomerGroupTranslation\CustomerGroupTranslationBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Listing\Event\ListingFacetTranslation\ListingFacetTranslationBasicLoadedEvent;
use Shopware\Listing\Event\ListingSortingTranslation\ListingSortingTranslationBasicLoadedEvent;
use Shopware\Locale\Event\Locale\LocaleBasicLoadedEvent;
use Shopware\Locale\Event\LocaleTranslation\LocaleTranslationBasicLoadedEvent;
use Shopware\Mail\Event\MailTranslation\MailTranslationBasicLoadedEvent;
use Shopware\Media\Event\MediaAlbumTranslation\MediaAlbumTranslationBasicLoadedEvent;
use Shopware\Media\Event\MediaTranslation\MediaTranslationBasicLoadedEvent;
use Shopware\Order\Event\OrderStateTranslation\OrderStateTranslationBasicLoadedEvent;
use Shopware\Payment\Event\PaymentMethod\PaymentMethodBasicLoadedEvent;
use Shopware\Payment\Event\PaymentMethodTranslation\PaymentMethodTranslationBasicLoadedEvent;
use Shopware\Product\Event\ProductManufacturerTranslation\ProductManufacturerTranslationBasicLoadedEvent;
use Shopware\Product\Event\ProductTranslation\ProductTranslationBasicLoadedEvent;
use Shopware\Shipping\Event\ShippingMethod\ShippingMethodBasicLoadedEvent;
use Shopware\Shipping\Event\ShippingMethodTranslation\ShippingMethodTranslationBasicLoadedEvent;
use Shopware\Shop\Collection\ShopDetailCollection;
use Shopware\Shop\Event\ShopTemplate\ShopTemplateBasicLoadedEvent;
use Shopware\Tax\Event\TaxAreaRuleTranslation\TaxAreaRuleTranslationBasicLoadedEvent;
use Shopware\Unit\Event\UnitTranslation\UnitTranslationBasicLoadedEvent;

class ShopDetailLoadedEvent extends NestedEvent
{
    const NAME = 'shop.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShopDetailCollection
     */
    protected $shops;

    public function __construct(ShopDetailCollection $shops, TranslationContext $context)
    {
        $this->context = $context;
        $this->shops = $shops;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getShops(): ShopDetailCollection
    {
        return $this->shops;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->shops->getParents()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->shops->getParents(), $this->context);
        }
        if ($this->shops->getTemplates()->count() > 0) {
            $events[] = new ShopTemplateBasicLoadedEvent($this->shops->getTemplates(), $this->context);
        }
        if ($this->shops->getDocumentTemplates()->count() > 0) {
            $events[] = new ShopTemplateBasicLoadedEvent($this->shops->getDocumentTemplates(), $this->context);
        }
        if ($this->shops->getCategories()->count() > 0) {
            $events[] = new CategoryBasicLoadedEvent($this->shops->getCategories(), $this->context);
        }
        if ($this->shops->getLocales()->count() > 0) {
            $events[] = new LocaleBasicLoadedEvent($this->shops->getLocales(), $this->context);
        }
        if ($this->shops->getCurrencies()->count() > 0) {
            $events[] = new CurrencyBasicLoadedEvent($this->shops->getCurrencies(), $this->context);
        }
        if ($this->shops->getCustomerGroups()->count() > 0) {
            $events[] = new CustomerGroupBasicLoadedEvent($this->shops->getCustomerGroups(), $this->context);
        }
        if ($this->shops->getFallbackTranslations()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->shops->getFallbackTranslations(), $this->context);
        }
        if ($this->shops->getPaymentMethods()->count() > 0) {
            $events[] = new PaymentMethodBasicLoadedEvent($this->shops->getPaymentMethods(), $this->context);
        }
        if ($this->shops->getShippingMethods()->count() > 0) {
            $events[] = new ShippingMethodBasicLoadedEvent($this->shops->getShippingMethods(), $this->context);
        }
        if ($this->shops->getCountries()->count() > 0) {
            $events[] = new CountryBasicLoadedEvent($this->shops->getCountries(), $this->context);
        }
        if ($this->shops->getCategoryTranslations()->count() > 0) {
            $events[] = new CategoryTranslationBasicLoadedEvent($this->shops->getCategoryTranslations(), $this->context);
        }
        if ($this->shops->getCountryAreaTranslations()->count() > 0) {
            $events[] = new CountryAreaTranslationBasicLoadedEvent($this->shops->getCountryAreaTranslations(), $this->context);
        }
        if ($this->shops->getCountryStateTranslations()->count() > 0) {
            $events[] = new CountryStateTranslationBasicLoadedEvent($this->shops->getCountryStateTranslations(), $this->context);
        }
        if ($this->shops->getCountryTranslations()->count() > 0) {
            $events[] = new CountryTranslationBasicLoadedEvent($this->shops->getCountryTranslations(), $this->context);
        }
        if ($this->shops->getCurrencyTranslations()->count() > 0) {
            $events[] = new CurrencyTranslationBasicLoadedEvent($this->shops->getCurrencyTranslations(), $this->context);
        }
        if ($this->shops->getCustomerGroupTranslations()->count() > 0) {
            $events[] = new CustomerGroupTranslationBasicLoadedEvent($this->shops->getCustomerGroupTranslations(), $this->context);
        }
        if ($this->shops->getListingFacetTranslations()->count() > 0) {
            $events[] = new ListingFacetTranslationBasicLoadedEvent($this->shops->getListingFacetTranslations(), $this->context);
        }
        if ($this->shops->getListingSortingTranslations()->count() > 0) {
            $events[] = new ListingSortingTranslationBasicLoadedEvent($this->shops->getListingSortingTranslations(), $this->context);
        }
        if ($this->shops->getLocaleTranslations()->count() > 0) {
            $events[] = new LocaleTranslationBasicLoadedEvent($this->shops->getLocaleTranslations(), $this->context);
        }
        if ($this->shops->getMailTranslations()->count() > 0) {
            $events[] = new MailTranslationBasicLoadedEvent($this->shops->getMailTranslations(), $this->context);
        }
        if ($this->shops->getMediaAlbumTranslations()->count() > 0) {
            $events[] = new MediaAlbumTranslationBasicLoadedEvent($this->shops->getMediaAlbumTranslations(), $this->context);
        }
        if ($this->shops->getMediaTranslations()->count() > 0) {
            $events[] = new MediaTranslationBasicLoadedEvent($this->shops->getMediaTranslations(), $this->context);
        }
        if ($this->shops->getOrderStateTranslations()->count() > 0) {
            $events[] = new OrderStateTranslationBasicLoadedEvent($this->shops->getOrderStateTranslations(), $this->context);
        }
        if ($this->shops->getPaymentMethodTranslations()->count() > 0) {
            $events[] = new PaymentMethodTranslationBasicLoadedEvent($this->shops->getPaymentMethodTranslations(), $this->context);
        }
        if ($this->shops->getProductManufacturerTranslations()->count() > 0) {
            $events[] = new ProductManufacturerTranslationBasicLoadedEvent($this->shops->getProductManufacturerTranslations(), $this->context);
        }
        if ($this->shops->getProductTranslations()->count() > 0) {
            $events[] = new ProductTranslationBasicLoadedEvent($this->shops->getProductTranslations(), $this->context);
        }
        if ($this->shops->getShippingMethodTranslations()->count() > 0) {
            $events[] = new ShippingMethodTranslationBasicLoadedEvent($this->shops->getShippingMethodTranslations(), $this->context);
        }
        if ($this->shops->getTaxAreaRuleTranslations()->count() > 0) {
            $events[] = new TaxAreaRuleTranslationBasicLoadedEvent($this->shops->getTaxAreaRuleTranslations(), $this->context);
        }
        if ($this->shops->getUnitTranslations()->count() > 0) {
            $events[] = new UnitTranslationBasicLoadedEvent($this->shops->getUnitTranslations(), $this->context);
        }
        if ($this->shops->getAllCurrencies()->count() > 0) {
            $events[] = new CurrencyBasicLoadedEvent($this->shops->getAllCurrencies(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
