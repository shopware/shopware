<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cleanup;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

#[Package('services-settings')]
class CleanupProductKeywordDictionaryTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'product_keyword_dictionary.cleanup';
    }

    public static function getDefaultInterval(): int
    {
        return self::WEEKLY;
    }
}
