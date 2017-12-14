<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductManufacturerTranslation;

use Shopware\Api\Product\Collection\ProductManufacturerTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductManufacturerTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'product_manufacturer_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ProductManufacturerTranslationBasicCollection
     */
    protected $productManufacturerTranslations;

    public function __construct(ProductManufacturerTranslationBasicCollection $productManufacturerTranslations, TranslationContext $context)
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

    public function getProductManufacturerTranslations(): ProductManufacturerTranslationBasicCollection
    {
        return $this->productManufacturerTranslations;
    }
}
