<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Update\Api\UpdateController;
use Shopware\Core\Framework\Update\Checkers\LicenseCheck;
use Shopware\Core\Framework\Update\Checkers\WriteableCheck;
use Shopware\Core\Framework\Update\Services\ApiClient;
use Shopware\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopware\Core\Framework\Update\Services\Filesystem;
use Shopware\Core\Framework\Update\Services\UpdateHtaccess;
use Shopware\Core\Framework\Update\Subscriber\UpdateSubscriber;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('env(SHOPWARE_DISABLE_UPDATE_CHECK)', '');

    $services = $containerConfigurator->services();

    $services->set(UpdateController::class)
        ->public()
        ->args([
            service(ApiClient::class),
            service(WriteableCheck::class),
            service(LicenseCheck::class),
            service(ExtensionCompatibility::class),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(ExtensionLifecycleService::class),
            param('kernel.shopware_version'),
            env('SHOPWARE_DISABLE_UPDATE_CHECK')->bool(),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ApiClient::class)
        ->args([
            service('http_client'),
            param('shopware.auto_update.enabled'),
            param('kernel.shopware_version'),
            param('kernel.project_dir'),
            service(ClockInterface::class),
        ]);

    $services->set(ExtensionCompatibility::class)
        ->args([
            service(StoreClient::class),
            service(AbstractExtensionDataProvider::class),
            service('event_dispatcher'),
        ]);

    $services->set(Filesystem::class);

    $services->set(WriteableCheck::class)
        ->args([
            service(Filesystem::class),
            param('kernel.project_dir'),
        ])
        ->tag('shopware.update_api.checker', ['priority' => 3]);

    $services->set(LicenseCheck::class)
        ->args([
            service(SystemConfigService::class),
            service(StoreClient::class),
        ])
        ->tag('shopware.update_api.checker', ['priority' => 4]);

    $services->set(UpdateHtaccess::class)
        ->args([
            '%kernel.project_dir%/public/.htaccess',
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UpdateSubscriber::class)
        ->args([
            service(NotificationService::class),
        ])
        ->tag('kernel.event_subscriber');
};
