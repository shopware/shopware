<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class UserBillingaddressWrittenEvent extends AbstractWrittenEvent
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
