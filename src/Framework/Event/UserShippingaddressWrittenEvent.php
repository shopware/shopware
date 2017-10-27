<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class UserShippingaddressWrittenEvent extends WrittenEvent
{
    const NAME = 's_user_shippingaddress.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_user_shippingaddress';
    }
}
