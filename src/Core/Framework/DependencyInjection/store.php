<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use Psr\Clock\ClockInterface;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopware\Core\Framework\App\InAppPurchases\Gateway\InAppPurchasesGateway;
use Shopware\Core\Framework\App\InAppPurchases\Payload\InAppPurchasesPayloadService;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Deployment\AirGappedMode;
use Shopware\Core\Framework\JWT\JWTDecoder;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginManagementService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Store\Api\ExtensionStoreActionsController;
use Shopware\Core\Framework\Store\Api\ExtensionStoreDataController;
use Shopware\Core\Framework\Store\Api\ExtensionStoreLicensesController;
use Shopware\Core\Framework\Store\Api\FirstRunWizardController;
use Shopware\Core\Framework\Store\Api\StoreController;
use Shopware\Core\Framework\Store\Authentication\FrwRequestOptionsProvider;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\Command\StoreDownloadCommand;
use Shopware\Core\Framework\Store\Command\StoreLoginCommand;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Store\InAppPurchase\Api\InAppPurchasesController;
use Shopware\Core\Framework\Store\InAppPurchase\Handler\InAppPurchaseUpdateHandler;
use Shopware\Core\Framework\Store\InAppPurchase\InAppPurchaseUpdateTask;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Shopware\Core\Framework\Store\InAppPurchase\Services\KeyFetcher;
use Shopware\Core\Framework\Store\InAppPurchase\Subscriber\InAppPurchaseConfigSubscriber;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\AbstractExtensionStoreLicensesService;
use Shopware\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Shopware\Core\Framework\Store\Services\AirGappedStoreRequestMiddleware;
use Shopware\Core\Framework\Store\Services\ExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Shopware\Core\Framework\Store\Services\ExtensionLifecycleService;
use Shopware\Core\Framework\Store\Services\ExtensionListingLoader;
use Shopware\Core\Framework\Store\Services\ExtensionLoader;
use Shopware\Core\Framework\Store\Services\ExtensionStoreLicensesService;
use Shopware\Core\Framework\Store\Services\FirstRunWizardClient;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\Store\Services\InstanceService;
use Shopware\Core\Framework\Store\Services\RetryFailedStoreRequestMiddleware;
use Shopware\Core\Framework\Store\Services\ShopSecretInvalidMiddleware;
use Shopware\Core\Framework\Store\Services\StoreAppLifecycleService;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Services\StoreClientFactory;
use Shopware\Core\Framework\Store\Services\StoreService;
use Shopware\Core\Framework\Store\Services\StoreSessionExpiredMiddleware;
use Shopware\Core\Framework\Store\Services\TrackingEventClient;
use Shopware\Core\Framework\Store\Subscriber\ExtensionChangedSubscriber;
use Shopware\Core\Framework\Store\Subscriber\LicenseHostChangedSubscriber;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SystemConfig\Service\ConfigurationService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('env(INSTANCE_ID)', '');
    $parameters->set('instance_id', env('INSTANCE_ID'));
    $parameters->set('in_app_purchases.active_purchases', '/swplatform/inappfeatures/purchases');
    $parameters->set('shopware.store_endpoints', [
        'my_extensions' => '/swplatform/licenseenvironment',
        'my_plugin_updates' => '/swplatform/pluginupdates',
        'environment_information' => '/swplatform/environmentinformation',
        'updater_extension_compatibility' => '/swplatform/autoupdate',
        'updater_permission' => '/swplatform/autoupdate/permission',
        'plugin_download' => '/swplatform/pluginfiles/{pluginName}',
        'app_generate_signature' => '/swplatform/generatesignature',
        'cancel_license' => '/swplatform/pluginlicenses/%s/cancel',
        'login' => '/swplatform/login',
        'create_rating' => '/swplatform/extensionstore/extensions/%s/ratings',
        'user_info' => '/swplatform/userinfo',
    ]);

    $services = $containerConfigurator->services();

    $services->set(StoreController::class)
        ->public()
        ->args([
            service(StoreClient::class),
            service('user.repository'),
            service(AbstractExtensionDataProvider::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(FirstRunWizardController::class)
        ->public()
        ->args([
            service(FirstRunWizardService::class),
            service('plugin.repository'),
            service('app.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(FirstRunWizardService::class)
        ->args([
            service(StoreService::class),
            service(SystemConfigService::class),
            service('shopware.filesystem.public'),
            param('shopware.store.frw'),
            service('event_dispatcher'),
            service(FirstRunWizardClient::class),
            service('user_config.repository'),
            service(TrackingEventClient::class),
        ]);

    $services->set(StoreClient::class)
        ->args([
            param('shopware.store_endpoints'),
            service(StoreService::class),
            service(SystemConfigService::class),
            service(StoreRequestOptionsProvider::class),
            service(ExtensionLoader::class),
            service('shopware.store_client'),
            service(InstanceService::class),
            service('request_stack'),
            service('cache.object'),
            service('event_dispatcher'),
        ]);

    $services->set(FirstRunWizardClient::class)
        ->args([
            service('shopware.frw_client'),
            service(FrwRequestOptionsProvider::class),
            service(InstanceService::class),
        ]);

    $services->set(StoreService::class)
        ->lazy()
        ->args([
            service('user.repository'),
            service(TrackingEventClient::class),
        ]);

    $services->set(InstanceService::class)
        ->args([
            param('kernel.shopware_version'),
            param('instance_id'),
        ]);

    $services->set(StoreDownloadCommand::class)
        ->args([
            service(StoreClient::class),
            service('plugin.repository'),
            service(PluginManagementService::class),
            service(PluginLifecycleService::class),
            service('user.repository'),
        ])
        ->tag('console.command');

    $services->set(StoreLoginCommand::class)
        ->args([
            service(StoreClient::class),
            service('user.repository'),
            service(SystemConfigService::class),
        ])
        ->tag('console.command');

    $services->set(LocaleProvider::class)
        ->args([
            service('user.repository'),
        ]);

    $services->set(StoreRequestOptionsProvider::class)
        ->public()
        ->args([
            service('user.repository'),
            service(SystemConfigService::class),
            service(InstanceService::class),
            service(LocaleProvider::class),
        ]);

    $services->set(FrwRequestOptionsProvider::class)
        ->args([
            service(StoreRequestOptionsProvider::class),
            service('user_config.repository'),
        ]);

    $services->set(ExtensionLoader::class)
        ->args([
            service(AppLoader::class),
            service(SourceResolver::class),
            service(ConfigurationService::class),
            service(LocaleProvider::class),
            service(LanguageLocaleCodeProvider::class),
            service(InAppPurchase::class),
            service('logger'),
            service('event_dispatcher'),
        ]);

    $services->set(AbstractExtensionDataProvider::class, ExtensionDataProvider::class)
        ->args([
            service(ExtensionLoader::class),
            service('app.repository'),
            service('plugin.repository'),
            service(ExtensionListingLoader::class),
            service('event_dispatcher'),
        ]);

    $services->set(ExtensionListingLoader::class)
        ->args([
            service(StoreClient::class),
        ]);

    $services->set(ExtensionStoreDataController::class)
        ->public()
        ->args([
            service(AbstractExtensionDataProvider::class),
            service('user.repository'),
            service('language.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(AbstractStoreAppLifecycleService::class, StoreAppLifecycleService::class)
        ->args([
            service(StoreClient::class),
            service(AppLoader::class),
            service(AppLifecycle::class),
            service(AppStorage::class),
            service('sales_channel.repository'),
            service('theme.repository')->nullOnInvalid(),
            service(AppConfirmationDeltaProvider::class),
        ]);

    $services->set(AbstractExtensionStoreLicensesService::class, ExtensionStoreLicensesService::class)
        ->args([
            service(StoreClient::class),
        ]);

    $services->set(ExtensionStoreLicensesController::class)
        ->public()
        ->args([
            service(AbstractExtensionStoreLicensesService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ExtensionDownloader::class)
        ->args([
            service('plugin.repository'),
            service(StoreClient::class),
            service(PluginManagementService::class),
        ]);

    $services->set(ExtensionLifecycleService::class)
        ->args([
            service(AbstractStoreAppLifecycleService::class),
            service(PluginService::class),
            service(PluginLifecycleService::class),
            service(PluginManagementService::class),
        ]);

    $services->set(ExtensionStoreActionsController::class)
        ->public()
        ->args([
            service(ExtensionLifecycleService::class),
            service(ExtensionDownloader::class),
            service(PluginService::class),
            service(PluginManagementService::class),
            service(Filesystem::class),
            param('shopware.deployment.runtime_extension_management'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(StoreClientFactory::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set('shopware.store_client', Client::class)
        ->lazy()
        ->public()
        ->factory([service(StoreClientFactory::class), 'create'])
        ->args([
            tagged_iterator('shopware.store_client.middleware'),
        ]);

    $services->set('shopware.frw_client', Client::class)
        ->lazy()
        ->public()
        ->factory([service(StoreClientFactory::class), 'create'])
        ->args([
            tagged_iterator('shopware.frw_client.middleware'),
        ]);

    $services->set('shopware.store_download_client', Client::class);

    $services->set(LicenseHostChangedSubscriber::class)
        ->args([
            service(SystemConfigService::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(StoreSessionExpiredMiddleware::class)
        ->public()
        ->args([
            service(Connection::class),
            service('request_stack'),
        ])
        ->tag('shopware.store_client.middleware');

    $services->set(ShopSecretInvalidMiddleware::class)
        ->public()
        ->args([
            service(Connection::class),
            service(SystemConfigService::class),
        ])
        ->tag('shopware.store_client.middleware');

    $services->set(RetryFailedStoreRequestMiddleware::class)
        ->public()
        ->tag('shopware.store_client.middleware');

    $services->set(AirGappedStoreRequestMiddleware::class)
        ->args([
            service(AirGappedMode::class),
        ])
        ->tag('shopware.store_client.middleware')
        ->tag('shopware.frw_client.middleware');

    $services->set(TrackingEventClient::class)
        ->args([
            service('shopware.store_client'),
            service(InstanceService::class),
        ]);

    $services->set(InAppPurchase::class)
        ->public()
        ->args([
            service(InAppPurchaseProvider::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(InAppPurchaseProvider::class)
        ->args([
            service(SystemConfigService::class),
            service(JWTDecoder::class),
            service(KeyFetcher::class),
            service('logger'),
            service(ClockInterface::class),
        ]);

    $services->set(InAppPurchasesController::class)
        ->public()
        ->args([
            service(InAppPurchase::class),
            service('app.repository'),
        ]);

    $services->set(InAppPurchaseUpdater::class)
        ->public()
        ->args([
            service('shopware.store_client'),
            service(SystemConfigService::class),
            param('in_app_purchases.active_purchases'),
            service(StoreRequestOptionsProvider::class),
            service(InAppPurchase::class),
            service('event_dispatcher'),
            service(Connection::class),
            service('logger'),
        ]);

    $services->set(InAppPurchaseUpdateHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(InAppPurchaseUpdater::class),
            service(StoreRequestOptionsProvider::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(InAppPurchaseUpdateTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(InAppPurchasesPayloadService::class)
        ->args([
            service(AppPayloadServiceHelper::class),
            service('shopware.app_system.guzzle'),
        ]);

    $services->set(InAppPurchasesGateway::class)
        ->args([
            service(InAppPurchasesPayloadService::class),
            service('event_dispatcher'),
        ]);

    $services->set(KeyFetcher::class)
        ->args([
            service('shopware.store_client'),
            service(StoreRequestOptionsProvider::class),
            service(SystemConfigService::class),
            service('logger'),
        ]);

    $services->set(InAppPurchaseConfigSubscriber::class)
        ->args([
            service('request_stack'),
            service(InAppPurchaseUpdater::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(JWTDecoder::class);

    $services->set(ExtensionChangedSubscriber::class)
        ->args([
            service('cache.object'),
        ]);
};
