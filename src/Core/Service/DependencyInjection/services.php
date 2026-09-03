<?php declare(strict_types=1);

namespace Shopware\Core\Service\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Command\UninstallAppCommand;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Deployment\AirGappedMode;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Service\AllServiceInstaller;
use Shopware\Core\Service\Api\PermissionController;
use Shopware\Core\Service\Api\ServiceController;
use Shopware\Core\Service\Command\Install;
use Shopware\Core\Service\Command\UninstallAppCommandDecorator;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\MessageHandler\InstallServicesHandler;
use Shopware\Core\Service\MessageHandler\LogConsentToRegistryHandler;
use Shopware\Core\Service\MessageHandler\UpdateServiceHandler;
use Shopware\Core\Service\Notification;
use Shopware\Core\Service\Permission\PermissionsService;
use Shopware\Core\Service\Requirement\RequirementsValidator;
use Shopware\Core\Service\Requirement\ServiceConsentRequirement;
use Shopware\Core\Service\Requirement\ServicesEnabledRequirement;
use Shopware\Core\Service\Requirement\ShopwareAccountRequirement;
use Shopware\Core\Service\ScheduledTask\InstallServicesTask;
use Shopware\Core\Service\ScheduledTask\InstallServicesTaskHandler;
use Shopware\Core\Service\ServiceClientFactory;
use Shopware\Core\Service\ServiceHookableEventDescriber;
use Shopware\Core\Service\ServiceLifecycle;
use Shopware\Core\Service\ServiceRegistry\Client;
use Shopware\Core\Service\ServiceRegistry\PermissionLogger;
use Shopware\Core\Service\ServiceRegistry\RegistryUrlProcessor;
use Shopware\Core\Service\ServiceSourceResolver;
use Shopware\Core\Service\ServiceStorage;
use Shopware\Core\Service\Subscriber\ExtensionCompatibilitiesResolvedSubscriber;
use Shopware\Core\Service\Subscriber\InstalledExtensionsListingLoadedSubscriber;
use Shopware\Core\Service\Subscriber\LicenseProviderSubscriber;
use Shopware\Core\Service\Subscriber\PermissionsSubscriber;
use Shopware\Core\Service\Subscriber\ServiceLifecycleSubscriber;
use Shopware\Core\Service\Subscriber\ServiceWriteProtectionSubscriber;
use Shopware\Core\Service\Subscriber\ShopwareAccountSubscriber;
use Shopware\Core\Service\Subscriber\SystemUpdateSubscriber;
use Shopware\Core\Service\TemporaryDirectoryFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('env(SERVICE_REGISTRY_URL)', ServiceExtension::DEFAULT_REGISTRY_URL);
    $parameters->set('env(ENABLE_SERVICES)', 'auto');

    $services = $containerConfigurator->services();

    $services->set(ServiceController::class)
        ->public()
        ->args([
            service(ServiceStorage::class),
            service('messenger.default_bus'),
            service(ServiceLifecycle::class),
            service(LifecycleManager::class),
            service(RequirementsValidator::class),
        ]);

    $services->set(PermissionController::class)
        ->public()
        ->args([
            service(PermissionsService::class),
        ]);

    $services->set(Install::class)
        ->args([
            service(LifecycleManager::class),
        ])
        ->tag('console.command');

    $services->set(RegistryUrlProcessor::class)
        ->args([
            ServiceExtension::DEFAULT_REGISTRY_URL,
            param('shopware.service_registry.trusted_domains'),
        ])
        ->tag('container.env_var_processor');

    $services->set(Client::class)
        ->args([
            param('shopware.service_registry.url'),
            env('APP_URL'),
            service('service_registry.http_client'),
            service(AirGappedMode::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ServiceLifecycle::class)
        ->args([
            service(AppManager::class),
            service('app.repository'),
            service(ServiceStorage::class),
            service('logger'),
            service(ManifestFactory::class),
            service(ServiceSourceResolver::class),
            service('event_dispatcher'),
            service(RequirementsValidator::class),
            service(Client::class),
            service(ServiceClientFactory::class),
        ]);

    $services->set(ServiceStorage::class)
        ->args([
            service('app.repository'),
        ]);

    $services->set(UninstallAppCommandDecorator::class)
        ->decorate(UninstallAppCommand::class)
        ->args([
            service(AppLifecycle::class),
            service(AppStorage::class),
            service(ServiceStorage::class),
            service(ServiceLifecycle::class),
            service(LifecycleManager::class),
        ])
        ->tag('console.command', ['command' => 'app:uninstall']);

    $services->set(ServiceClientFactory::class)
        ->args([
            service(HttpClientInterface::class),
            service(Client::class),
            param('kernel.shopware_version'),
        ]);

    $services->set(AllServiceInstaller::class)
        ->args([
            service(Client::class),
            service(ServiceStorage::class),
            service(ServiceLifecycle::class),
            service('messenger.bus.default'),
            service('event_dispatcher'),
            service('logger'),
        ]);

    $services->set(InstallServicesTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(InstallServicesTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(LifecycleManager::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(UpdateServiceHandler::class)
        ->args([
            service(ServiceLifecycle::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(InstallServicesHandler::class)
        ->args([
            service(LifecycleManager::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(ServiceSourceResolver::class)
        ->args([
            service(Client::class),
            service(TemporaryDirectoryFactory::class),
            service(AppExtractor::class),
            service(Filesystem::class),
        ])
        ->tag('app.source_resolver', ['priority' => 100]);

    $services->set(ExtensionCompatibilitiesResolvedSubscriber::class)
        ->args([
            service(Client::class),
            service(AbstractExtensionDataProvider::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(InstalledExtensionsListingLoadedSubscriber::class)
        ->args([
            service('app.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SystemUpdateSubscriber::class)
        ->args([
            service(LifecycleManager::class),
            service('logger'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TemporaryDirectoryFactory::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(LicenseProviderSubscriber::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ServiceHookableEventDescriber::class)
        ->tag('shopware.hookable_event.describer');

    $services->set(PermissionsService::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(PermissionLogger::class),
            service(ClockInterface::class),
        ]);

    $services->set(ServiceConsentRequirement::class)
        ->args([
            service(PermissionsService::class),
        ])
        ->tag('shopware.service.requirement');

    $services->set(ServicesEnabledRequirement::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('shopware.service.requirement');

    $services->set(ShopwareAccountRequirement::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.service.requirement');

    $services->set(RequirementsValidator::class)
        ->args([
            tagged_iterator('shopware.service.requirement', null, 'getName'),
        ]);

    $services->set(LifecycleManager::class)
        ->args([
            env('ENABLE_SERVICES'),
            param('kernel.environment'),
            service(Privileges::class),
            service(SystemConfigService::class),
            service(ServiceStorage::class),
            service(ServiceLifecycle::class),
            service(AllServiceInstaller::class),
            service(PermissionsService::class),
            service(Client::class),
            service(RequirementsValidator::class),
            service(AirGappedMode::class),
        ]);

    $services->set(ServiceLifecycleSubscriber::class)
        ->args([
            service(LifecycleManager::class),
            service(Notification::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PermissionsSubscriber::class)
        ->args([
            service(LifecycleManager::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ShopwareAccountSubscriber::class)
        ->args([
            service(LifecycleManager::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ServiceWriteProtectionSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(Notification::class)
        ->args([
            service(NotificationService::class),
        ]);

    $services->set(PermissionLogger::class)
        ->args([
            service(Client::class),
            service('messenger.bus.default'),
            service(ShopIdProvider::class),
            service(SystemConfigService::class),
        ]);

    $services->set(LogConsentToRegistryHandler::class)
        ->args([
            service(PermissionLogger::class),
        ])
        ->tag('messenger.message_handler');
};
