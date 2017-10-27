<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class UserBillingaddressWrittenEvent extends WrittenEvent
{
    const NAME = 's_user_billingaddress.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 's_user_billingaddress';
    }
}
