<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\Shop;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Shop\Definition\ShopDefinition;

class ShopDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'shop.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopDefinition::class;
    }
}
