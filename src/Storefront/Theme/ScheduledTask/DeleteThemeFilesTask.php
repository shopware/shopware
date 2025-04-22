<?php

declare(strict_types=1);

namespace Shopware\Storefront\Theme\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class DeleteThemeFilesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'theme.delete_files';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }
}
