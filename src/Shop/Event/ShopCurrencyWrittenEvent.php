<?php declare(strict_types=1);

namespace Shopware\Shop\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ShopCurrencyWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'shop_currency.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'shop_currency';
    }
}
