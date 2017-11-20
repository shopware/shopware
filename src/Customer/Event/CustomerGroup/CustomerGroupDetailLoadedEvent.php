<?php declare(strict_types=1);

namespace Shopware\Customer\Event\CustomerGroup;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Collection\CustomerGroupDetailCollection;
use Shopware\Customer\Event\Customer\CustomerBasicLoadedEvent;
use Shopware\Customer\Event\CustomerGroupDiscount\CustomerGroupDiscountBasicLoadedEvent;
use Shopware\Customer\Event\CustomerGroupTranslation\CustomerGroupTranslationBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Event\ProductListingPrice\ProductListingPriceBasicLoadedEvent;
use Shopware\Product\Event\ProductPrice\ProductPriceBasicLoadedEvent;
use Shopware\Shipping\Event\ShippingMethod\ShippingMethodBasicLoadedEvent;
use Shopware\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Tax\Event\TaxAreaRule\TaxAreaRuleBasicLoadedEvent;

class CustomerGroupDetailLoadedEvent extends NestedEvent
{
    const NAME = 'customer_group.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CustomerGroupDetailCollection
     */
    protected $customerGroups;

    public function __construct(CustomerGroupDetailCollection $customerGroups, TranslationContext $context)
    {
        $this->context = $context;
        $this->customerGroups = $customerGroups;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCustomerGroups(): CustomerGroupDetailCollection
    {
        return $this->customerGroups;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->customerGroups->getCustomers()->count() > 0) {
            $events[] = new CustomerBasicLoadedEvent($this->customerGroups->getCustomers(), $this->context);
        }
        if ($this->customerGroups->getDiscounts()->count() > 0) {
            $events[] = new CustomerGroupDiscountBasicLoadedEvent($this->customerGroups->getDiscounts(), $this->context);
        }
        if ($this->customerGroups->getTranslations()->count() > 0) {
            $events[] = new CustomerGroupTranslationBasicLoadedEvent($this->customerGroups->getTranslations(), $this->context);
        }
        if ($this->customerGroups->getProductListingPrices()->count() > 0) {
            $events[] = new ProductListingPriceBasicLoadedEvent($this->customerGroups->getProductListingPrices(), $this->context);
        }
        if ($this->customerGroups->getProductPrices()->count() > 0) {
            $events[] = new ProductPriceBasicLoadedEvent($this->customerGroups->getProductPrices(), $this->context);
        }
        if ($this->customerGroups->getShippingMethods()->count() > 0) {
            $events[] = new ShippingMethodBasicLoadedEvent($this->customerGroups->getShippingMethods(), $this->context);
        }
        if ($this->customerGroups->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->customerGroups->getShops(), $this->context);
        }
        if ($this->customerGroups->getTaxAreaRules()->count() > 0) {
            $events[] = new TaxAreaRuleBasicLoadedEvent($this->customerGroups->getTaxAreaRules(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
