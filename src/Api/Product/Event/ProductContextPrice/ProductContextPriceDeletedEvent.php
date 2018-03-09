<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductContextPrice;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductContextPriceDefinition;

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
