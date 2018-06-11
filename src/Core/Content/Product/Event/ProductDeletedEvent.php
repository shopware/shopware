<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Event;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
