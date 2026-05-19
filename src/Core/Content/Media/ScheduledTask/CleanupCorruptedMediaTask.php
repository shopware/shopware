<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @codeCoverageIgnore
 *
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
        return self::DAILY;
    }
}
