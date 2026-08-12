<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Admin;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Package('inventory')]
class AdminCreateAliasTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'shopware.elasticsearch.admin.create.alias';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY * 5;
    }

    public static function shouldRun(ParameterBagInterface $bag): bool
    {
        return (bool) $bag->get('elasticsearch.administration.enabled');
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
