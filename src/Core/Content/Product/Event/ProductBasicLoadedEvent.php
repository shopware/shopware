<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event;

use Shopware\Framework\Context;
use Shopware\Content\Product\Aggregate\ProductContextPrice\Event\ProductContextPriceBasicLoadedEvent;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Event\ProductManufacturerBasicLoadedEvent;
use Shopware\Content\Product\Aggregate\ProductMedia\Event\ProductMediaBasicLoadedEvent;
use Shopware\Content\Product\Collection\ProductBasicCollection;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\System\Tax\Event\TaxBasicLoadedEvent;
use Shopware\System\Unit\Event\UnitBasicLoadedEvent;

class ProductBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ProductBasicCollection
     */
    protected $products;

    public function __construct(ProductBasicCollection $products, Context $context)
    {
        $this->context = $context;
        $this->products = $products;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
        $taxes = $this->products->getTaxes();
        if ($taxes->count() > 0) {
            $events[] = new TaxBasicLoadedEvent($taxes, $this->context);
        }

        $manufactures = $this->products->getManufacturers();
        if ($manufactures->count() > 0) {
            $events[] = new ProductManufacturerBasicLoadedEvent($manufactures, $this->context);
        }

        $units = $this->products->getUnits();
        if ($units->count() > 0) {
            $events[] = new UnitBasicLoadedEvent($units, $this->context);
        }

        $prices = $this->products->getContextPrices();
        if ($prices->count() > 0) {
            $events[] = new ProductContextPriceBasicLoadedEvent($prices, $this->context);
        }

        $covers = $this->products->getCovers();
        if ($covers->count() > 0) {
            $events[] = new ProductMediaBasicLoadedEvent($covers, $this->context);
        }

        return new NestedEventCollection($events);
    }
}
