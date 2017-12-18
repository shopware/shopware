<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopCurrency;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Shop\Definition\ShopCurrencyDefinition;

class ShopCurrencyWrittenEvent extends WrittenEvent
{
    public const NAME = 'shop_currency.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopCurrencyDefinition::class;
    }
}
