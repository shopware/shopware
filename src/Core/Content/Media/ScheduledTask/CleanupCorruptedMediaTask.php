<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
#[Package('discovery')]
class CleanupCorruptedMediaTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'media.cleanup_corrupted_media';
    }

    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
