<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductManufacturer\Event;

use Shopware\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ProductManufacturerDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_manufacturer.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductManufacturerDefinition::class;
    }
}
