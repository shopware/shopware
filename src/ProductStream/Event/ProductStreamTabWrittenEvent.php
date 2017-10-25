<?php declare(strict_types=1);

namespace Shopware\ProductStream\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ProductStreamTabWrittenEvent extends EntityWrittenEvent
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
