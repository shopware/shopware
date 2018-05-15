<?php declare(strict_types=1);

namespace Shopware\Content\Product\Event\ProductService;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Content\Product\Definition\ProductServiceDefinition;

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
