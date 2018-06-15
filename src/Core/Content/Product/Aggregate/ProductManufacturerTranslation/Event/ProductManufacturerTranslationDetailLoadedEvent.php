<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\Event;

use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\Event\ProductManufacturerBasicLoadedEvent;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\Collection\ProductManufacturerTranslationDetailCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Language\Event\LanguageBasicLoadedEvent;

class ProductManufacturerTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer_translation.detail.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var ProductManufacturerTranslationDetailCollection
     */
    protected $productManufacturerTranslations;

    public function __construct(ProductManufacturerTranslationDetailCollection $productManufacturerTranslations, Context $context)
    {
        $this->context = $context;
        $this->productManufacturerTranslations = $productManufacturerTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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
            $events[] = new LanguageBasicLoadedEvent($this->productManufacturerTranslations->getLanguages(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
