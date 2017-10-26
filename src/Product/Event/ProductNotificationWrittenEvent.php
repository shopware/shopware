<?php declare(strict_types=1);

namespace Shopware\Product\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ProductNotificationWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'product_notification.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_notification';
    }
}
