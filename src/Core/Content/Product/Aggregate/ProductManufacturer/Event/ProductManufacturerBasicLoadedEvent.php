<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductManufacturer\Event;

use Shopware\Framework\Context;
use Shopware\Content\Product\Aggregate\ProductManufacturer\Collection\ProductManufacturerBasicCollection;
use Shopware\Framework\Event\NestedEvent;

class ProductManufacturerBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'product_manufacturer.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var \Shopware\Content\Product\Aggregate\ProductManufacturer\Collection\ProductManufacturerBasicCollection
     */
    protected $productManufacturers;

    public function __construct(ProductManufacturerBasicCollection $productManufacturers, Context $context)
    {
        $this->context = $context;
        $this->productManufacturers = $productManufacturers;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getProductManufacturers(): ProductManufacturerBasicCollection
    {
        return $this->productManufacturers;
    }
}
