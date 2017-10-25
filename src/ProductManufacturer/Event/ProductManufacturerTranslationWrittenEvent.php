<?php declare(strict_types=1);

namespace Shopware\ProductManufacturer\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ProductManufacturerTranslationWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'product_manufacturer_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_manufacturer_translation';
    }
}
