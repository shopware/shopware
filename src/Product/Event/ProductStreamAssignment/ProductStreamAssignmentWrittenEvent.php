<?php declare(strict_types=1);

namespace Shopware\Product\Event\ProductStreamAssignment;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Product\Definition\ProductStreamAssignmentDefinition;

class ProductStreamAssignmentWrittenEvent extends WrittenEvent
{
    const NAME = 'product_stream_assignment.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductStreamAssignmentDefinition::class;
    }
}
