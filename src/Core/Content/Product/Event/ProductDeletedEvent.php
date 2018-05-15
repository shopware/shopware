<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\ProductDefinition;

class ProductDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductDefinition::class;
    }
}
