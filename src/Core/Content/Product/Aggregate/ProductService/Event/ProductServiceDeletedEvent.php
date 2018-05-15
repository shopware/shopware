<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductService\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Aggregate\ProductService\ProductServiceDefinition;

class ProductServiceDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_service.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductServiceDefinition::class;
    }
}
