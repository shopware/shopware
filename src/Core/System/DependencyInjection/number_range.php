<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Adapter\Lock\LockManager;
use Shopware\Core\Framework\Adapter\Redis\RedisConnectionProvider;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationDefinition;
use Shopware\Core\System\NumberRange\Api\NumberRangeController;
use Shopware\Core\System\NumberRange\Command\MigrateIncrementStorageCommand;
use Shopware\Core\System\NumberRange\NumberRangeDefinition;
use Shopware\Core\System\NumberRange\Telemetry\IncrementStorageMetricsDecorator;
use Shopware\Core\System\NumberRange\Telemetry\NumberRangeTypeResolver;
use Shopware\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementStorageRegistry;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternDate;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternIncrement;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\ValueGeneratorPatternRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(NumberRangeDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(NumberRangeSalesChannelDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(NumberRangeStateDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(NumberRangeTypeDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(NumberRangeTypeTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(NumberRangeTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    // Value Generator
    $services->set(MigrateIncrementStorageCommand::class)
        ->args([
            service(IncrementStorageRegistry::class),
        ])
        ->tag('console.command');

    $services->set(IncrementSqlStorage::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('shopware.value_generator_connector', ['storage' => 'mysql']);

    $services->set(AbstractIncrementStorage::class)
        ->factory([service(IncrementStorageRegistry::class), 'getStorage']);

    $services->set(NumberRangeTypeResolver::class);

    $services->set(IncrementStorageMetricsDecorator::class)
        ->decorate(AbstractIncrementStorage::class)
        ->args([
            service(IncrementStorageMetricsDecorator::class . '.inner'),
            service(Meter::class),
            service(NumberRangeTypeResolver::class),
            param('shopware.number_range.increment_storage'),
        ]);

    $services->set(IncrementRedisStorage::class)
        ->args([
            service('shopware.number_range.redis'),
            service(LockManager::class),
            service('number_range.repository'),
        ])
        ->tag('shopware.value_generator_connector', ['storage' => 'redis']);

    $services->set(IncrementStorageRegistry::class)
        ->args([
            tagged_locator('shopware.value_generator_connector', 'storage'),
            param('shopware.number_range.increment_storage'),
        ]);

    $services->set('shopware.number_range.redis', \Redis::class)
        ->factory([service(RedisConnectionProvider::class), 'getConnection'])
        ->args([
            param('shopware.number_range.config.connection'),
        ]);

    $services->set(NumberRangeValueGeneratorInterface::class, NumberRangeValueGenerator::class)
        ->public()
        ->args([
            service(ValueGeneratorPatternRegistry::class),
            service('event_dispatcher'),
            service(Connection::class),
        ]);

    $services->alias(AbstractNumberRangeValueGenerator::class, NumberRangeValueGeneratorInterface::class)
        ->public();

    $services->set(ValueGeneratorPatternRegistry::class)
        ->args([
            tagged_iterator('shopware.value_generator_pattern'),
        ]);

    $services->set(ValueGeneratorPatternIncrement::class)
        ->args([
            service(AbstractIncrementStorage::class),
        ])
        ->tag('shopware.value_generator_pattern');

    $services->set(ValueGeneratorPatternDate::class)
        ->tag('shopware.value_generator_pattern');

    $services->set(NumberRangeController::class)
        ->public()
        ->args([
            service(AbstractNumberRangeValueGenerator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};
