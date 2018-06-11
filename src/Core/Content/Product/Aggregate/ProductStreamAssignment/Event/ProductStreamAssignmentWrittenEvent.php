<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductStreamAssignment\Event;

use Shopware\Core\Content\Product\Aggregate\ProductStreamAssignment\ProductStreamAssignmentDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
