<?php declare(strict_types=1);

namespace Shopware\ProductStream\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductStreamTabWrittenEvent extends WrittenEvent
{
    const NAME = 'product_stream_tab.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_stream_tab';
    }
}
