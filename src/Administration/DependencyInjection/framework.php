<?php declare(strict_types=1);

namespace Shopware\Administration\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Administration\Framework\Api\Subscriber\AdminInfoConfigBundlesSubscriber;
use Shopware\Administration\Framework\App\ActiveAdminAppLoader;
use Shopware\Administration\Framework\App\Subscriber\SystemLanguageChangedSubscriber;
use Shopware\Administration\Framework\Asset\AssetUploadListener;
use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Administration\Framework\Routing\KnownIps\KnownIpsCollector;
use Shopware\Administration\Framework\Routing\NotFound\AdministrationNotFoundSubscriber;
use Shopware\Administration\Framework\SystemCheck\AdministrationReadinessCheck;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(AdministrationNotFoundSubscriber::class)
        ->args([
            param('shopware_administration.path_name'),
            service('service_container'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AdministrationRouteScope::class)
        ->args([
            param('shopware_administration.path_name'),
        ])
        ->tag('shopware.route_scope');

    $services->set(AdministrationReadinessCheck::class)
        ->args([
            service(RouterInterface::class),
            service(KernelInterface::class),
            service(ViteFileAccessorDecorator::class),
            service('filesystem'),
            service(ClockInterface::class),
        ])
        ->tag('shopware.system_check');

    $services->set(KnownIpsCollector::class);

    $services->set(ViteFileAccessorDecorator::class)
        ->decorate('pentatrion_vite.file_accessor')
        ->args([
            param('pentatrion_vite.configs'),
            service('shopware.asset.asset'),
            service('kernel'),
            service('filesystem'),
        ]);

    $services->set(SystemLanguageChangedSubscriber::class)
        ->args([
            service('locale.repository'),
            service('app_administration_snippet.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ActiveAdminAppLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(AdminInfoConfigBundlesSubscriber::class)
        ->args([
            service('kernel'),
            service('router'),
            service(ActiveAdminAppLoader::class),
            service('filesystem'),
            service(ViteFileAccessorDecorator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AssetUploadListener::class)
        ->tag('kernel.event_listener');
};
