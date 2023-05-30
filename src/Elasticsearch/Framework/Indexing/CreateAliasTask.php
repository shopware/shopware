<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework\Indexing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Package('core')]
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

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return (bool) $bag->get('elasticsearch.enabled');
    }
}
