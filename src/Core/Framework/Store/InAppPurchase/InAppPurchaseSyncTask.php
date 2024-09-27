<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('checkout')]
class InAppPurchaseSyncTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'in-app-purchase.fetch.active';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }
}
