<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Package('framework')]
class CleanupIndicesTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'shopware.elasticsearch.cleanup.indices';
    }

    public static function getDefaultInterval(): int
    {
        return self::DAILY;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return (bool) $bag->get('elasticsearch.enabled') || (bool) $bag->get('elasticsearch.administration.enabled');
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
