<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Event\CustomerGroupBasicLoadedEvent;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\PriceGroup\Event\PriceGroupBasicLoadedEvent;
use Shopware\Product\Struct\ProductBasicCollection;
use Shopware\ProductDetail\Event\ProductDetailBasicLoadedEvent;
use Shopware\ProductManufacturer\Event\ProductManufacturerBasicLoadedEvent;
use Shopware\SeoUrl\Event\SeoUrlBasicLoadedEvent;
use Shopware\Tax\Event\TaxBasicLoadedEvent;

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
        return new NestedEventCollection([
            new ProductManufacturerBasicLoadedEvent($this->products->getManufacturers(), $this->context),
            new ProductDetailBasicLoadedEvent($this->products->getMainDetails(), $this->context),
            new TaxBasicLoadedEvent($this->products->getTaxs(), $this->context),
            new SeoUrlBasicLoadedEvent($this->products->getCanonicalUrls(), $this->context),
            new PriceGroupBasicLoadedEvent($this->products->getPriceGroups(), $this->context),
            new CustomerGroupBasicLoadedEvent($this->products->getBlockedCustomerGroupss(), $this->context),
        ]);
    }
}
