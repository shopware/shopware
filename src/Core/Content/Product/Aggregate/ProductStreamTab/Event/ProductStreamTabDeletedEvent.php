<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductStreamTab\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Aggregate\ProductStreamTab\ProductStreamTabDefinition;

class ProductStreamTabDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_stream_tab.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductStreamTabDefinition::class;
    }
}
