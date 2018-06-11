<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductStreamTab\Event;

use Shopware\Core\Content\Product\Aggregate\ProductStreamTab\ProductStreamTabDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
