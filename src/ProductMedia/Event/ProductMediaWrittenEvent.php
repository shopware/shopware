<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductMediaWrittenEvent extends WrittenEvent
{
    const NAME = 'product_media.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_media';
    }
}
