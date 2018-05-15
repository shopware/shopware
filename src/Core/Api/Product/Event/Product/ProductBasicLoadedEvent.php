<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\Product;

use Shopware\Api\Product\Collection\ProductBasicCollection;
use Shopware\Api\Product\Event\ProductContextPrice\ProductContextPriceBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerBasicLoadedEvent;
use Shopware\Api\Product\Event\ProductMedia\ProductMediaBasicLoadedEvent;
use Shopware\System\Tax\Event\Tax\TaxBasicLoadedEvent;
use Shopware\Api\Unit\Event\Unit\UnitBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ProductBasicCollection
     */
    protected $products;

    public function __construct(ProductBasicCollection $products, ApplicationContext $context)
    {
        $this->context = $context;
        $this->products = $products;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
            $events[]= new ProductMediaBasicLoadedEvent($covers, $this->context);
        }

        return new NestedEventCollection($events);
    }
}
