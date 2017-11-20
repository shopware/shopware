<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductManufacturer;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Collection\ProductManufacturerDetailCollection;
use Shopware\Product\Event\Product\ProductBasicLoadedEvent;
use Shopware\Product\Event\ProductManufacturerTranslation\ProductManufacturerTranslationBasicLoadedEvent;

class ProductManufacturerDetailLoadedEvent extends NestedEvent
{
    const NAME = 'product_manufacturer.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ProductManufacturerDetailCollection
     */
    protected $productManufacturers;

    public function __construct(ProductManufacturerDetailCollection $productManufacturers, TranslationContext $context)
    {
        $this->context = $context;
        $this->productManufacturers = $productManufacturers;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getProductManufacturers(): ProductManufacturerDetailCollection
    {
        return $this->productManufacturers;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productManufacturers->getProducts()->count() > 0) {
            $events[] = new ProductBasicLoadedEvent($this->productManufacturers->getProducts(), $this->context);
        }
        if ($this->productManufacturers->getTranslations()->count() > 0) {
            $events[] = new ProductManufacturerTranslationBasicLoadedEvent($this->productManufacturers->getTranslations(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
