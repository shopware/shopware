<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductStreamTab;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductStreamTabDefinition;

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
