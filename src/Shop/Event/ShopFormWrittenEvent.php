<?php declare(strict_types=1);

namespace Shopware\Shop\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ShopFormWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'shop_form.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'shop_form';
    }
}
