<?php declare(strict_types=1);

namespace Shopware\Shop\Event\Shop;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Shop\Definition\ShopDefinition;

class ShopWrittenEvent extends WrittenEvent
{
    const NAME = 'shop.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopDefinition::class;
    }
}
