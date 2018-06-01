<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\Collection\ProductManufacturerTranslationBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class ProductManufacturerTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer_translation.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\Collection\ProductManufacturerTranslationBasicCollection
     */
    protected $productManufacturerTranslations;

    public function __construct(ProductManufacturerTranslationBasicCollection $productManufacturerTranslations, Context $context)
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

    public function getProductManufacturerTranslations(): ProductManufacturerTranslationBasicCollection
    {
        return $this->productManufacturerTranslations;
    }
}
