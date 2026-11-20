<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('inventory')]
class CleanupProductDocumentArchivesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'product_document.archive.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
