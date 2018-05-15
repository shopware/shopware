<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\Product;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductDefinition;

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
