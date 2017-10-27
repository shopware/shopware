<?php declare(strict_types=1);

namespace Shopware\ProductStream\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductStreamWrittenEvent extends WrittenEvent
{
    const NAME = 'product_stream.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_stream';
    }
}
