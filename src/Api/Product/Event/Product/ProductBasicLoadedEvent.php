<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\Product;

use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Event\ProductListingPrice\ProductListingPriceBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductPrice\ProductPriceBasicLoadedEvent;
use Shopware\Api\Tax\Event\Tax\TaxBasicLoadedEvent;
use Shopware\Api\Unit\Event\Unit\UnitBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ProductBasicCollection
     */
    protected $products;

    public function __construct(ProductBasicCollection $products, TranslationContext $context)
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

    public function getProducts(): ProductBasicCollection
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
        if ($this->products->getPrices()->count() > 0) {
            $events[] = new ProductPriceBasicLoadedEvent($this->products->getPrices(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
