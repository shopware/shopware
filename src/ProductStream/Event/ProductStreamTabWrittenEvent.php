<?php declare(strict_types=1);

namespace Shopware\ProductStream\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ProductStreamTabWrittenEvent extends AbstractWrittenEvent
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
