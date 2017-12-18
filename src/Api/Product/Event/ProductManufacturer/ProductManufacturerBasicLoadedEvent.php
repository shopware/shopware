<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductManufacturer;

use Shopware\Api\Product\Collection\ProductManufacturerBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ProductManufacturerBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ProductManufacturerBasicCollection
     */
    protected $productManufacturers;

    public function __construct(ProductManufacturerBasicCollection $productManufacturers, TranslationContext $context)
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

    public function getProductManufacturers(): ProductManufacturerBasicCollection
    {
        return $this->productManufacturers;
    }
}
