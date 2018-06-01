<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContextPrice\Event;

use Shopware\Core\Content\Product\Aggregate\ProductContextPrice\ProductContextPriceDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

class ProductContextPriceDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_context_price.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductContextPriceDefinition::class;
    }
}
