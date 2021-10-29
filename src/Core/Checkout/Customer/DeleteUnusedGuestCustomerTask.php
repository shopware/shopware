<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class DeleteUnusedGuestCustomerTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'customer.delete_unused_guests';
    }

    public static function getDefaultInterval(): int
    {
        return 86400; // 24 hours
    }
}
