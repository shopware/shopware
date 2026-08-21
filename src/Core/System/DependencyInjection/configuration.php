<?php declare(strict_types=1);

namespace Shopware\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SystemConfig\Api\SystemConfigController;
use Shopware\Core\System\SystemConfig\CachedSystemConfigLoader;
use Shopware\Core\System\SystemConfig\Command\ConfigGet;
use Shopware\Core\System\SystemConfig\Command\ConfigSet;
use Shopware\Core\System\SystemConfig\ConfiguredSystemConfigLoader;
use Shopware\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;
use Shopware\Core\System\SystemConfig\MemoizedSystemConfigLoader;
use Shopware\Core\System\SystemConfig\SalesChannel\ShopSettingsRoute;
use Shopware\Core\System\SystemConfig\Service\AppConfigReader;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;
use Shopware\Core\System\SystemConfig\SymfonySystemConfigService;
use Shopware\Core\System\SystemConfig\SystemConfigDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigLoader;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SystemConfigValidator::class)
        ->args([
            service(ConfigurationService::class),
            service(DataValidator::class),
        ])
        ->tag('shopware.system_config.validation');

    $services->set(SystemConfigDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set('kernel.bundles', \Iterator::class)
        ->factory([service('kernel'), 'getBundles']);

    $services->set(AppConfigReader::class)
        ->args([
            service(SourceResolver::class),
            service(ConfigReader::class),
        ]);

    $services->set(ConfigurationService::class)
        ->args([
            service('kernel.bundles'),
            service(ConfigReader::class),
            service(AppConfigReader::class),
            service('app.repository'),
            service(SystemConfigService::class),
            service('logger'),
        ]);

    $services->set(ConfigReader::class);

    $services->set(SystemConfigController::class)
        ->public()
        ->args([
            service(ConfigurationService::class),
            service(SystemConfigService::class),
            service(SystemConfigValidator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SystemConfigService::class)
        ->public()
        ->lazy()
        ->args([
            service(Connection::class),
            service(ConfigReader::class),
            service(SystemConfigLoader::class),
            service('event_dispatcher'),
            service(SymfonySystemConfigService::class),
            service(CacheTagCollector::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ShopSettingsRoute::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(MemoizedSystemConfigStore::class)
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(SymfonySystemConfigService::class)
        ->args([
            param('shopware.system_config'),
        ]);

    $services->set(SystemConfigLoader::class)
        ->args([
            service(Connection::class),
            service(KernelInterface::class),
        ]);

    $services->set(ConfiguredSystemConfigLoader::class)
        ->decorate(SystemConfigLoader::class, null, -1500)
        ->args([
            service(ConfiguredSystemConfigLoader::class . '.inner'),
            service(SymfonySystemConfigService::class),
        ]);

    $services->set(CachedSystemConfigLoader::class)
        ->decorate(SystemConfigLoader::class, null, -1000)
        ->args([
            service(CachedSystemConfigLoader::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(MemoizedSystemConfigLoader::class)
        ->decorate(SystemConfigLoader::class, null, -2000)
        ->args([
            service(MemoizedSystemConfigLoader::class . '.inner'),
            service(MemoizedSystemConfigStore::class),
        ]);

    $services->set(SystemConfigFacadeHookFactory::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service(Connection::class),
        ]);

    $services->set(ConfigGet::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('console.command');

    $services->set(ConfigSet::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('console.command');
};
