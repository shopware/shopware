<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductStreamAssignment;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductStreamAssignmentDefinition;

class ProductStreamAssignmentWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_stream_assignment.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductStreamAssignmentDefinition::class;
    }
}
