<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\ScheduledTask;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('sales-channel')]
class SitemapGenerateTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'shopware.sitemap_generate';
    }

    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
