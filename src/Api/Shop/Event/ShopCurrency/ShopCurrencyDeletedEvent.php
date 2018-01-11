<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopCurrency;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Shop\Definition\ShopCurrencyDefinition;

class ShopCurrencyDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'shop_currency.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopCurrencyDefinition::class;
    }
}
