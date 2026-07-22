<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Core\Framework\Rule\Api\RuleConfigController;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\MatchAllLineItemsRule;
use Shopware\Core\Framework\Rule\Container\NotRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Container\XorRule;
use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Rule\RuleIdMatcher;
use Shopware\Core\Framework\Rule\SalesChannelRule;
use Shopware\Core\Framework\Rule\ScriptRule;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\Framework\Rule\TimeRangeRule;
use Shopware\Core\Framework\Rule\WeekdayRule;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RuleConditionRegistry::class)
        ->args([
            tagged_iterator('shopware.rule.definition'),
        ]);

    $services->set(RuleIdMatcher::class);

    $services->set(AndRule::class)
        ->tag('shopware.rule.definition');

    $services->set(NotRule::class)
        ->tag('shopware.rule.definition');

    $services->set(OrRule::class)
        ->tag('shopware.rule.definition');

    $services->set(XorRule::class)
        ->tag('shopware.rule.definition');

    $services->set(MatchAllLineItemsRule::class)
        ->tag('shopware.rule.definition');

    $services->set(ScriptRule::class)
        ->tag('shopware.rule.definition');

    $services->set(DateRangeRule::class)
        ->tag('shopware.rule.definition');

    $services->set(SimpleRule::class)
        ->tag('shopware.rule.definition');

    $services->set(SalesChannelRule::class)
        ->tag('shopware.rule.definition');

    $services->set(TimeRangeRule::class)
        ->tag('shopware.rule.definition');

    $services->set(WeekdayRule::class)
        ->tag('shopware.rule.definition');

    $services->set(RuleConfigController::class)
        ->public()
        ->args([
            tagged_iterator('shopware.rule.definition'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};
