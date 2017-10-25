<?php declare(strict_types=1);

namespace Shopware\ProductStream\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ProductStreamAssignmentWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'product_stream_assignment.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_stream_assignment';
    }
}
