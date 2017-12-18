<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductManufacturerTranslation;

use Shopware\Api\Product\Collection\ProductManufacturerTranslationDetailCollection;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductManufacturerTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer_translation.detail.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ProductManufacturerTranslationDetailCollection
     */
    protected $productManufacturerTranslations;

    public function __construct(ProductManufacturerTranslationDetailCollection $productManufacturerTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->productManufacturerTranslations = $productManufacturerTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getProductManufacturerTranslations(): ProductManufacturerTranslationDetailCollection
    {
        return $this->productManufacturerTranslations;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->productManufacturerTranslations->getProductManufacturers()->count() > 0) {
            $events[] = new ProductManufacturerBasicLoadedEvent($this->productManufacturerTranslations->getProductManufacturers(), $this->context);
        }
        if ($this->productManufacturerTranslations->getLanguages()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->productManufacturerTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
