<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\App\Lifecycle\Handler\ScriptLifecycleHandler;
use Shopware\Core\Framework\Script\Api\AclFacadeHookFactory;
use Shopware\Core\Framework\Script\Api\ScriptApiRoute;
use Shopware\Core\Framework\Script\Api\ScriptResponseEncoder;
use Shopware\Core\Framework\Script\Api\ScriptResponseFactoryFacadeHookFactory;
use Shopware\Core\Framework\Script\Api\ScriptStoreApiRoute;
use Shopware\Core\Framework\Script\AppContextCreator;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Script\Execution\ScriptEnvironmentFactory;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Script\Execution\ScriptLoader;
use Shopware\Core\Framework\Script\ScriptDefinition;
use Shopware\Core\System\SalesChannel\Api\StructEncoder;
use Shopware\Storefront\Controller\ScriptController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ScriptLoader::class)
        ->args([
            service(Connection::class),
            service(ScriptLifecycleHandler::class),
            service('cache.object'),
            param('twig.cache'),
            param('kernel.debug'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ScriptExecutor::class)
        ->public()
        ->args([
            service(ScriptLoader::class),
            service(ScriptTraces::class),
            service('service_container'),
            service(ScriptEnvironmentFactory::class),
        ]);

    $services->set(ScriptEnvironmentFactory::class)
        ->public()
        ->args([
            service('twig.extension.debug'),
            tagged_iterator('shopware.app_script.twig.extension'),
            param('kernel.shopware_version'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ScriptDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ScriptTraces::class)
        ->public()
        ->args([
            service(ClockInterface::class),
        ])
        ->tag('data_collector')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ScriptStoreApiRoute::class)
        ->public()
        ->args([
            service(ScriptExecutor::class),
            service(ScriptResponseEncoder::class),
            service('cache.object'),
            service('logger'),
        ]);

    $services->set(ScriptApiRoute::class)
        ->public()
        ->args([
            service(ScriptExecutor::class),
            service(ScriptLoader::class),
            service(ScriptResponseEncoder::class),
        ]);

    $services->set(ScriptResponseFactoryFacadeHookFactory::class)
        ->public()
        ->args([
            service('router'),
            // @deprecated tag:v6.8.0 Only needed for the deprecated ScriptResponseFactoryFacade::render() BC path
            service(ScriptController::class)->nullOnInvalid(),
        ]);

    $services->set(ScriptResponseEncoder::class)
        ->args([
            service(StructEncoder::class),
        ]);

    $services->set(AclFacadeHookFactory::class)
        ->public()
        ->args([
            service(AppContextCreator::class),
        ]);

    $services->set(AppContextCreator::class)
        ->args([
            service(Connection::class),
        ]);
};
