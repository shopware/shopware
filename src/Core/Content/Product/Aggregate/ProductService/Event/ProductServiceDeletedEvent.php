<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductService\Event;

use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
