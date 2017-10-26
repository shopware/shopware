<?php declare(strict_types=1);

namespace Shopware\Shop\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ShopCurrencyWrittenEvent extends AbstractWrittenEvent
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
