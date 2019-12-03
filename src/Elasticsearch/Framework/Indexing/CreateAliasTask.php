<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CreateAliasTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'shopware.elasticsearch.create.alias';
    }

    public static function getDefaultInterval(): int
    {
        return 300; // 5 minutes
    }
}
