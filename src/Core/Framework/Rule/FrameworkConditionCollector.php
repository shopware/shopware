<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Rule;

use Shopware\Core\Framework\Rule\Collector\RuleConditionCollectorInterface;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\NotRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Container\XorRule;

class FrameworkConditionCollector implements RuleConditionCollectorInterface
{
    public function getClasses(): array
    {
        return [
            AndRule::class,
            OrRule::class,
            NotRule::class,
            XorRule::class,
            CurrencyRule::class,
            DateRangeRule::class,
            SalesChannelRule::class,
        ];
    }
}
