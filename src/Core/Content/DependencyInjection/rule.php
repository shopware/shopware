<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleTag\RuleTagDefinition;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleAreaUpdater;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleIndexer;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleIndexerSubscriber;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RulePayloadSubscriber;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RulePayloadUpdater;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Content\Rule\RuleValidator;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RuleDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(RuleConditionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(RuleTagDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(RuleValidator::class)
        ->args([
            service('validator'),
            service(RuleConditionRegistry::class),
            service('rule_condition.repository'),
            service('app_script_condition.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RulePayloadSubscriber::class)
        ->args([
            service(RulePayloadUpdater::class),
            service('service_container'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RuleIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('rule.repository'),
            service(RulePayloadUpdater::class),
            service(RuleAreaUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('shopware.entity_indexer');

    $services->set(RuleIndexerSubscriber::class)
        ->args([
            service(Connection::class),
            service(CartRuleLoader::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RulePayloadUpdater::class)
        ->args([
            service(Connection::class),
            service(RuleConditionRegistry::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RuleAreaUpdater::class)
        ->args([
            service(Connection::class),
            service(RuleDefinition::class),
            service(RuleConditionRegistry::class),
            service(CacheInvalidator::class),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');
};
