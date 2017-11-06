<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\PriceGroup\Event\PriceGroupBasicLoadedEvent;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\ProductListingPrice\Event\ProductListingPriceBasicLoadedEvent;
use Shopware\ProductManufacturer\Event\ProductManufacturerBasicLoadedEvent;
use Shopware\ProductPrice\Event\ProductPriceBasicLoadedEvent;
use Shopware\SeoUrl\Event\SeoUrlBasicLoadedEvent;
use Shopware\Tax\Event\TaxBasicLoadedEvent;
use Shopware\Unit\Event\UnitBasicLoadedEvent;

class ProductBasicLoadedEvent extends NestedEvent
{
    const NAME = 'product.basic.loaded';

    /**
     * @var ProductBasicCollection
     */
    protected $products;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ProductBasicCollection $products, TranslationContext $context)
    {
        $this->products = $products;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getProducts(): ProductBasicCollection
    {
        return $this->products;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->products->getUnits()->count() > 0) {
            $events[] = new UnitBasicLoadedEvent($this->products->getUnits(), $this->context);
        }
        if ($this->products->getPrices()->count() > 0) {
            $events[] = new ProductPriceBasicLoadedEvent($this->products->getPrices(), $this->context);
        }
        if ($this->products->getManufacturers()->count() > 0) {
            $events[] = new ProductManufacturerBasicLoadedEvent($this->products->getManufacturers(), $this->context);
        }
        if ($this->products->getTaxes()->count() > 0) {
            $events[] = new TaxBasicLoadedEvent($this->products->getTaxes(), $this->context);
        }
        if ($this->products->getCanonicalUrls()->count() > 0) {
            $events[] = new SeoUrlBasicLoadedEvent($this->products->getCanonicalUrls(), $this->context);
        }
        if ($this->products->getPriceGroups()->count() > 0) {
            $events[] = new PriceGroupBasicLoadedEvent($this->products->getPriceGroups(), $this->context);
        }
        if ($this->products->getBlockedCustomerGroups()->count() > 0) {
            $events[] = new CustomerGroupBasicLoadedEvent($this->products->getBlockedCustomerGroups(), $this->context);
        }
        if ($this->products->getListingPrices()->count() > 0) {
            $events[] = new ProductListingPriceBasicLoadedEvent($this->products->getListingPrices(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
