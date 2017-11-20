<?php declare(strict_types=1);

namespace Shopware\Product\Event\Product;

use Shopware\Category\Event\Category\CategoryBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Collection\ProductDetailCollection;
use Shopware\Product\Event\ProductListingPrice\ProductListingPriceBasicLoadedEvent;
use Shopware\Product\Event\ProductManufacturer\ProductManufacturerBasicLoadedEvent;
use Shopware\Product\Event\ProductMedia\ProductMediaBasicLoadedEvent;
use Shopware\Product\Event\ProductPrice\ProductPriceBasicLoadedEvent;
use Shopware\Product\Event\ProductStream\ProductStreamBasicLoadedEvent;
use Shopware\Product\Event\ProductTranslation\ProductTranslationBasicLoadedEvent;
use Shopware\Tax\Event\Tax\TaxBasicLoadedEvent;
use Shopware\Unit\Event\Unit\UnitBasicLoadedEvent;

class ProductDetailLoadedEvent extends NestedEvent
{
    const NAME = 'product.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ProductDetailCollection
     */
    protected $products;

    public function __construct(ProductDetailCollection $products, TranslationContext $context)
    {
        $this->context = $context;
        $this->products = $products;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getProducts(): ProductDetailCollection
    {
        return $this->products;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->products->getTaxes()->count() > 0) {
            $events[] = new TaxBasicLoadedEvent($this->products->getTaxes(), $this->context);
        }
        if ($this->products->getManufacturers()->count() > 0) {
            $events[] = new ProductManufacturerBasicLoadedEvent($this->products->getManufacturers(), $this->context);
        }
        if ($this->products->getUnits()->count() > 0) {
            $events[] = new UnitBasicLoadedEvent($this->products->getUnits(), $this->context);
        }
        if ($this->products->getListingPrices()->count() > 0) {
            $events[] = new ProductListingPriceBasicLoadedEvent($this->products->getListingPrices(), $this->context);
        }
        if ($this->products->getMedia()->count() > 0) {
            $events[] = new ProductMediaBasicLoadedEvent($this->products->getMedia(), $this->context);
        }
        if ($this->products->getPrices()->count() > 0) {
            $events[] = new ProductPriceBasicLoadedEvent($this->products->getPrices(), $this->context);
        }
        if ($this->products->getTranslations()->count() > 0) {
            $events[] = new ProductTranslationBasicLoadedEvent($this->products->getTranslations(), $this->context);
        }
        if ($this->products->getAllCategories()->count() > 0) {
            $events[] = new CategoryBasicLoadedEvent($this->products->getAllCategories(), $this->context);
        }
        if ($this->products->getAllCategoryTree()->count() > 0) {
            $events[] = new CategoryBasicLoadedEvent($this->products->getAllCategoryTree(), $this->context);
        }
        if ($this->products->getAllSeoCategories()->count() > 0) {
            $events[] = new CategoryBasicLoadedEvent($this->products->getAllSeoCategories(), $this->context);
        }
        if ($this->products->getAllTabs()->count() > 0) {
            $events[] = new ProductStreamBasicLoadedEvent($this->products->getAllTabs(), $this->context);
        }
        if ($this->products->getAllStreams()->count() > 0) {
            $events[] = new ProductStreamBasicLoadedEvent($this->products->getAllStreams(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
