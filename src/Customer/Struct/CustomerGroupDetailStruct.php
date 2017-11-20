<?php declare(strict_types=1);

namespace Shopware\Customer\Struct;

use Shopware\Customer\Collection\CustomerBasicCollection;
use Shopware\Customer\Collection\CustomerGroupDiscountBasicCollection;
use Shopware\Customer\Collection\CustomerGroupTranslationBasicCollection;
use Shopware\Product\Collection\ProductListingPriceBasicCollection;
use Shopware\Product\Collection\ProductPriceBasicCollection;
use Shopware\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Shop\Collection\ShopBasicCollection;
use Shopware\Tax\Collection\TaxAreaRuleBasicCollection;

class CustomerGroupDetailStruct extends CustomerGroupBasicStruct
{
    /**
     * @var CustomerBasicCollection
     */
    protected $customers;

    /**
     * @var CustomerGroupDiscountBasicCollection
     */
    protected $discounts;

    /**
     * @var CustomerGroupTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var ProductListingPriceBasicCollection
     */
    protected $productListingPrices;

    /**
     * @var ProductPriceBasicCollection
     */
    protected $productPrices;

    /**
     * @var ShippingMethodBasicCollection
     */
    protected $shippingMethods;

    /**
     * @var ShopBasicCollection
     */
    protected $shops;

    /**
     * @var TaxAreaRuleBasicCollection
     */
    protected $taxAreaRules;

    public function __construct()
    {
        $this->customers = new CustomerBasicCollection();

        $this->discounts = new CustomerGroupDiscountBasicCollection();

        $this->translations = new CustomerGroupTranslationBasicCollection();

        $this->productListingPrices = new ProductListingPriceBasicCollection();

        $this->productPrices = new ProductPriceBasicCollection();

        $this->shippingMethods = new ShippingMethodBasicCollection();

        $this->shops = new ShopBasicCollection();

        $this->taxAreaRules = new TaxAreaRuleBasicCollection();
    }

    public function getCustomers(): CustomerBasicCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerBasicCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getDiscounts(): CustomerGroupDiscountBasicCollection
    {
        return $this->discounts;
    }

    public function setDiscounts(CustomerGroupDiscountBasicCollection $discounts): void
    {
        $this->discounts = $discounts;
    }

    public function getTranslations(): CustomerGroupTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CustomerGroupTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getProductListingPrices(): ProductListingPriceBasicCollection
    {
        return $this->productListingPrices;
    }

    public function setProductListingPrices(ProductListingPriceBasicCollection $productListingPrices): void
    {
        $this->productListingPrices = $productListingPrices;
    }

    public function getProductPrices(): ProductPriceBasicCollection
    {
        return $this->productPrices;
    }

    public function setProductPrices(ProductPriceBasicCollection $productPrices): void
    {
        $this->productPrices = $productPrices;
    }

    public function getShippingMethods(): ShippingMethodBasicCollection
    {
        return $this->shippingMethods;
    }

    public function setShippingMethods(ShippingMethodBasicCollection $shippingMethods): void
    {
        $this->shippingMethods = $shippingMethods;
    }

    public function getShops(): ShopBasicCollection
    {
        return $this->shops;
    }

    public function setShops(ShopBasicCollection $shops): void
    {
        $this->shops = $shops;
    }

    public function getTaxAreaRules(): TaxAreaRuleBasicCollection
    {
        return $this->taxAreaRules;
    }

    public function setTaxAreaRules(TaxAreaRuleBasicCollection $taxAreaRules): void
    {
        $this->taxAreaRules = $taxAreaRules;
    }
}
