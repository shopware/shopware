<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductService\Event;

use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

class ProductServiceWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_service.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductServiceDefinition::class;
    }
}
