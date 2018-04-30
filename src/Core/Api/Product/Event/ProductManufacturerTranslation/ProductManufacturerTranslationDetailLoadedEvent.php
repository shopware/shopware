<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductManufacturerTranslation;

use Shopware\Api\Language\Event\Language\LanguageBasicLoadedEvent;
use Shopware\Api\Product\Collection\ProductManufacturerTranslationDetailCollection;
use Shopware\Api\Product\Event\ProductManufacturer\ProductManufacturerBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class ProductManufacturerTranslationDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer_translation.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ProductManufacturerTranslationDetailCollection
     */
    protected $productManufacturerTranslations;

    public function __construct(ProductManufacturerTranslationDetailCollection $productManufacturerTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->productManufacturerTranslations = $productManufacturerTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
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
