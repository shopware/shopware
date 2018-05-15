<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductManufacturerTranslation;

use Shopware\Content\Product\Collection\ProductManufacturerTranslationBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductManufacturerTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ProductManufacturerTranslationBasicCollection
     */
    protected $productManufacturerTranslations;

    public function __construct(ProductManufacturerTranslationBasicCollection $productManufacturerTranslations, ApplicationContext $context)
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

    public function getProductManufacturerTranslations(): ProductManufacturerTranslationBasicCollection
    {
        return $this->productManufacturerTranslations;
    }
}
