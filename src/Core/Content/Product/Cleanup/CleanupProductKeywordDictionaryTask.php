<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('system-settings')]
class CleanupProductKeywordDictionaryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'product_keyword_dictionary.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return 604800; // 1 week
    }
}
