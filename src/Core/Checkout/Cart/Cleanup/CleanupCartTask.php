<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Cleanup;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupCartTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'cart.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; //24 hours
    }
}
