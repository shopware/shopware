<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\Shop;

use Shopware\Content\Category\Event\Category\CategoryBasicLoadedEvent;
use Shopware\System\Country\Event\Country\CountryBasicLoadedEvent;
use Shopware\System\Currency\Event\Currency\CurrencyBasicLoadedEvent;
use Shopware\Api\Customer\Event\CustomerGroup\CustomerGroupBasicLoadedEvent;
use Shopware\System\Locale\Event\Locale\LocaleBasicLoadedEvent;
use Shopware\Api\Payment\Event\PaymentMethod\PaymentMethodBasicLoadedEvent;
use Shopware\Api\Shipping\Event\ShippingMethod\ShippingMethodBasicLoadedEvent;
use Shopware\Api\Shop\Collection\ShopDetailCollection;
use Shopware\Api\Shop\Event\ShopTemplate\ShopTemplateBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ShopDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'shop.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ShopDetailCollection
     */
    protected $shops;

    public function __construct(ShopDetailCollection $shops, ApplicationContext $context)
    {
        $this->context = $context;
        $this->shops = $shops;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
        if ($this->shops->getChildren()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->shops->getChildren(), $this->context);
        }
        if ($this->shops->getAllCurrencies()->count() > 0) {
            $events[] = new CurrencyBasicLoadedEvent($this->shops->getAllCurrencies(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
