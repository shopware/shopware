<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Gateway\Command\Executor\CheckoutGatewayCommandExecutor;
use Shopware\Core\Checkout\Gateway\Command\Registry\CheckoutGatewayCommandRegistry;
use Shopware\Core\Content\Media\File\TrustedUrlResolver;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Lock\LockManager;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\ActionButton\ActionButtonLoader;
use Shopware\Core\Framework\App\ActionButton\AppActionLoader;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\ActionButton\Response\ActionButtonResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\NotificationResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenModalResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\OpenNewTabResponseFactory;
use Shopware\Core\Framework\App\ActionButton\Response\ReloadDataResponseFactory;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Aggregate\ActionButton\ActionButtonDefinition;
use Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodDefinition;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionDefinition;
use Shopware\Core\Framework\App\Aggregate\AppScriptConditionTranslation\AppScriptConditionTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\AppShippingMethod\AppShippingMethodDefinition;
use Shopware\Core\Framework\App\Aggregate\AppTranslation\AppTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\CmsBlock\AppCmsBlockDefinition;
use Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\FlowAction\AppFlowActionDefinition;
use Shopware\Core\Framework\App\Aggregate\FlowActionTranslation\AppFlowActionTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\FlowEvent\AppFlowEventDefinition;
use Shopware\Core\Framework\App\Api\AppActionController;
use Shopware\Core\Framework\App\Api\AppCmsController;
use Shopware\Core\Framework\App\Api\AppJWTGenerateRoute;
use Shopware\Core\Framework\App\Api\AppPrivilegeController;
use Shopware\Core\Framework\App\Api\AppSecretRotationController;
use Shopware\Core\Framework\App\Api\ShopIdController;
use Shopware\Core\Framework\App\Api\VerifyShopController;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\App\AppDownloader;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\AppLocaleProvider;
use Shopware\Core\Framework\App\AppSecretResolver;
use Shopware\Core\Framework\App\AppService;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Checkout\Gateway\AppCheckoutGateway;
use Shopware\Core\Framework\App\Checkout\Payload\AppCheckoutGatewayPayloadService;
use Shopware\Core\Framework\App\Cms\BlockTemplateLoader;
use Shopware\Core\Framework\App\Command\ActivateAppCommand;
use Shopware\Core\Framework\App\Command\AppListCommand;
use Shopware\Core\Framework\App\Command\AppPrinter;
use Shopware\Core\Framework\App\Command\AppUrlVerificationStatusCommand;
use Shopware\Core\Framework\App\Command\AppUrlVerifyCommand;
use Shopware\Core\Framework\App\Command\ChangeShopIdCommand;
use Shopware\Core\Framework\App\Command\CheckShopIdCommand;
use Shopware\Core\Framework\App\Command\CreateAppCommand;
use Shopware\Core\Framework\App\Command\DeactivateAppCommand;
use Shopware\Core\Framework\App\Command\InstallAppCommand;
use Shopware\Core\Framework\App\Command\RefreshAppCommand;
use Shopware\Core\Framework\App\Command\RotateAppSecretCommand;
use Shopware\Core\Framework\App\Command\UninstallAppCommand;
use Shopware\Core\Framework\App\Command\ValidateAppCommand;
use Shopware\Core\Framework\App\Context\Gateway\AppContextGateway;
use Shopware\Core\Framework\App\Context\Payload\AppContextGatewayPayloadService;
use Shopware\Core\Framework\App\Cookie\AppCookieCollectListener;
use Shopware\Core\Framework\App\DeletedApps\DeletedAppsGateway;
use Shopware\Core\Framework\App\DeletedApps\RememberDeletedAppsSecretSubscriber;
use Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopware\Core\Framework\App\Delta\DomainsDeltaProvider;
use Shopware\Core\Framework\App\Delta\PermissionsDeltaProvider;
use Shopware\Core\Framework\App\Feature\AppFeatureDefinitionRegistry;
use Shopware\Core\Framework\App\Feature\AppFeatureLifecycleHandler;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Flow\Action\AppFlowActionLoadedSubscriber;
use Shopware\Core\Framework\App\Flow\Action\AppFlowActionProvider;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Http\AppSystemHttpMiddleware;
use Shopware\Core\Framework\App\Lifecycle\AppFeatureValidator;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleIterator;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Lifecycle\AppManager;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\Lifecycle\Handler\ActionButtonLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\CmsBlockLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\CustomFieldLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\FlowActionLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\FlowEventLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\ModuleLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\PaymentMethodLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\RuleConditionLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\ScriptLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\ShippingMethodLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\TaxProviderLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\TemplateLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Handler\WebhookLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\PermissionLifecycleService;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Lifecycle\Registration\HandshakeFactory;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopware\Core\Framework\App\Lifecycle\Update\AbstractAppUpdater;
use Shopware\Core\Framework\App\Lifecycle\Update\AppUpdater;
use Shopware\Core\Framework\App\Manifest\ManifestFactory;
use Shopware\Core\Framework\App\Manifest\ModuleLoader;
use Shopware\Core\Framework\App\MessageHandler\RotateAppSecretHandler;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Payment\Handler\AppPaymentHandler;
use Shopware\Core\Framework\App\Payment\Payload\PaymentPayloadService;
use Shopware\Core\Framework\App\Privileges\AppCapability;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\App\ScheduledTask\DeleteCascadeAppsHandler;
use Shopware\Core\Framework\App\ScheduledTask\DeleteCascadeAppsTask;
use Shopware\Core\Framework\App\ScheduledTask\SystemHeartbeatHandler;
use Shopware\Core\Framework\App\ScheduledTask\SystemHeartbeatTask;
use Shopware\Core\Framework\App\ScheduledTask\UpdateAppsHandler;
use Shopware\Core\Framework\App\ScheduledTask\UpdateAppsTask;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\Fingerprint\InstallationPath;
use Shopware\Core\Framework\App\ShopId\Fingerprint\SalesChannelDomainUrls;
use Shopware\Core\Framework\App\ShopId\FingerprintGenerator;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\ShopIdChangeResolver\MoveShopPermanentlyStrategy;
use Shopware\Core\Framework\App\ShopIdChangeResolver\ReinstallAppsStrategy;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\App\ShopIdChangeResolver\UninstallAppsStrategy;
use Shopware\Core\Framework\App\Source\Local;
use Shopware\Core\Framework\App\Source\NoDatabaseSourceResolver;
use Shopware\Core\Framework\App\Source\RemoteZip;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\App\Source\TemporaryDirectoryFactory;
use Shopware\Core\Framework\App\Subscriber\AppLoadedSubscriber;
use Shopware\Core\Framework\App\Subscriber\AppScriptConditionConstraintsSubscriber;
use Shopware\Core\Framework\App\Subscriber\CustomFieldProtectionSubscriber;
use Shopware\Core\Framework\App\Subscriber\DiscardUnconfirmedAppSecretsListener;
use Shopware\Core\Framework\App\TaxProvider\Payload\TaxProviderPayloadService;
use Shopware\Core\Framework\App\Telemetry\AppTelemetrySubscriber;
use Shopware\Core\Framework\App\Template\TemplateDefinition;
use Shopware\Core\Framework\App\Template\TemplateLoader;
use Shopware\Core\Framework\App\Url\AppUrlVerificationPrinter;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\App\Validation\AppNameValidator;
use Shopware\Core\Framework\App\Validation\AppRequirementsValidator;
use Shopware\Core\Framework\App\Validation\ConfigValidator;
use Shopware\Core\Framework\App\Validation\HookableValidator;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\App\Validation\Requirements\PublicAccess;
use Shopware\Core\Framework\App\Validation\Requirements\SecureUrlValidator;
use Shopware\Core\Framework\App\Validation\TranslationValidator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandExecutor;
use Shopware\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Shopware\Core\Framework\Store\Services\AbstractStoreAppLifecycleService;
use Shopware\Core\Framework\Store\Services\ExtensionDownloader;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Telemetry\Metrics\Meter;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Shopware\Core\Framework\Webhook\WebhookCacheClearer;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\CustomField\CustomFieldSetPersister;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\Clock\ClockInterface as SymfonyClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('shopware.app_dir', '%kernel.project_dir%/custom/apps');

    $services = $containerConfigurator->services();

    $services->set(ManifestFactory::class)
        ->args([
            service(SourceResolver::class),
        ]);

    $services->set(AppLoadedSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(CustomFieldProtectionSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AppScriptConditionConstraintsSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(ShopIdProvider::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(Connection::class),
            service(FingerprintGenerator::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ModuleLoader::class)
        ->args([
            service('app.repository'),
            service(ShopIdProvider::class),
            service(QuerySigner::class),
        ]);

    $services->set(TranslationValidator::class)
        ->tag('shopware.app_manifest.validator');

    $services->set(AppNameValidator::class)
        ->tag('shopware.app_manifest.validator');

    $services->set(ManifestValidator::class)
        ->args([
            tagged_iterator('shopware.app_manifest.validator'),
        ]);

    $services->set(ConfigValidator::class)
        ->args([
            service(ConfigReader::class),
        ])
        ->tag('shopware.app_manifest.validator');

    $services->set(HookableValidator::class)
        ->args([
            service(HookableEventCollector::class),
        ])
        ->tag('shopware.app_manifest.validator');

    $services->set(SecureUrlValidator::class);

    $services->set(PublicAccess::class)
        ->args([
            service(SecureUrlValidator::class),
            service('shopware.app_system.guzzle'),
        ])
        ->tag('app.requirements_validator')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppRequirementsValidator::class)
        ->args([
            tagged_iterator('app.requirements_validator'),
            service('logger'),
            param('kernel.environment'),
        ]);

    $services->set(PermissionLifecycleService::class)
        ->args([
            service(Connection::class),
            service(Privileges::class),
            service(ClockInterface::class),
        ]);

    // App Lifecycle Persisters - do not change priority without careful consideration
    $services->set(FlowActionLifecycleHandler::class)
        ->args([
            service('app_flow_action.repository'),
            service(Connection::class),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => 0]);

    $services->set(WebhookLifecycleHandler::class)
        ->args([
            service(Connection::class),
            service(WebhookCacheClearer::class),
            service(ClockInterface::class),
            service(WebhookTargetValidator::class),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -100]);

    $services->set(FlowEventLifecycleHandler::class)
        ->args([
            service('app_flow_event.repository'),
            service(Connection::class),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -200]);

    $services->set(PaymentMethodLifecycleHandler::class)
        ->args([
            service('payment_method.repository'),
            service('media.repository'),
            service(MediaService::class),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -300]);

    $services->set(TaxProviderLifecycleHandler::class)
        ->args([
            service('tax_provider.repository'),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -400]);

    $services->set(ModuleLifecycleHandler::class)
        ->args([
            service('app.repository'),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -500]);

    $services->set(ShippingMethodLifecycleHandler::class)
        ->args([
            service('shipping_method.repository'),
            service('app_shipping_method.repository'),
            service('media.repository'),
            service(MediaService::class),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -600]);

    $services->set(RuleConditionLifecycleHandler::class)
        ->args([
            service(ScriptFileReader::class),
            service('app_script_condition.repository'),
            service('app.repository'),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -700]);

    $services->set(ActionButtonLifecycleHandler::class)
        ->args([
            service('app_action_button.repository'),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -800]);

    $services->set(TemplateLifecycleHandler::class)
        ->args([
            service(TemplateLoader::class),
            service('app_template.repository'),
            service('app.repository'),
            service(CacheClearer::class),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -900]);

    $services->set(ScriptLifecycleHandler::class)
        ->args([
            service(ScriptFileReader::class),
            service('script.repository'),
            service('app.repository'),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -1000]);

    $services->set(CustomFieldLifecycleHandler::class)
        ->args([
            service(CustomFieldSetPersister::class),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -1100]);

    $services->set(CmsBlockLifecycleHandler::class)
        ->args([
            service('app_cms_block.repository'),
            service(BlockTemplateLoader::class),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -1200]);

    $services->set(AppFeatureLifecycleHandler::class)
        ->args([
            service(AppFeatureDefinitionRegistry::class),
            service(AppFeatureStorage::class),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -1300]);

    $services->set(AppFeatureDefinitionRegistry::class)
        ->args([
            tagged_iterator('shopware.app_feature.definition'),
        ]);

    $services->set(AppFeatureStorage::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
            service(AppFeatureDefinitionRegistry::class),
        ]);

    $services->set(ScriptFileReader::class)
        ->args([
            service(SourceResolver::class),
        ]);

    $services->set(TemplateLoader::class)
        ->args([
            service(SourceResolver::class),
        ]);

    $services->set(AppService::class)
        ->args([
            service(AppLifecycleIterator::class),
            service(AppLifecycle::class),
        ]);

    $services->set(AppPayloadServiceHelper::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(JsonEntityEncoder::class),
            service(ShopIdProvider::class),
            service(InAppPurchase::class),
            env('APP_URL'),
            service(SymfonyClockInterface::class),
        ]);

    $services->set(ActiveAppsLoader::class)
        ->args([
            service(Connection::class),
            service(AppLoader::class),
            param('kernel.project_dir'),
        ])
        ->tag('kernel.reset', ['method' => 'reset'])
        ->tag('kernel.event_listener', ['event' => 'console.terminate', 'method' => 'reset']);

    $services->set(PaymentPayloadService::class)
        ->args([
            service(AppPayloadServiceHelper::class),
            service('shopware.app_system.guzzle'),
        ]);

    $services->set(TaxProviderPayloadService::class)
        ->args([
            service(AppPayloadServiceHelper::class),
            service('shopware.app_system.guzzle'),
            service(ExceptionLogger::class),
        ]);

    $services->set(AppCheckoutGatewayPayloadService::class)
        ->args([
            service(AppPayloadServiceHelper::class),
            service('shopware.app_system.guzzle'),
            service(ExceptionLogger::class),
        ]);

    $services->set(AppContextGatewayPayloadService::class)
        ->args([
            service(AppPayloadServiceHelper::class),
            service('shopware.app_system.guzzle'),
        ]);

    $services->set(AppCheckoutGateway::class)
        ->args([
            service(AppCheckoutGatewayPayloadService::class),
            service(CheckoutGatewayCommandExecutor::class),
            service(CheckoutGatewayCommandRegistry::class),
            service('app.repository'),
            service('event_dispatcher'),
            service(ExceptionLogger::class),
            service(ActiveAppsLoader::class),
            service(AppCapability::class),
        ]);

    $services->set(AppContextGateway::class)
        ->args([
            service(AppContextGatewayPayloadService::class),
            service(ContextGatewayCommandExecutor::class),
            service(ContextGatewayCommandRegistry::class),
            service('app.repository'),
            service('event_dispatcher'),
            service(ExceptionLogger::class),
            service(AppCapability::class),
        ]);

    $services->set(AppCookieCollectListener::class)
        ->args([
            service('app.repository'),
        ])
        ->tag('kernel.event_listener');

    $services->set(AppPaymentHandler::class)
        ->args([
            service(StateMachineRegistry::class),
            service(PaymentPayloadService::class),
            service('order_transaction_capture_refund.repository'),
            service('order_transaction.repository'),
            service('app.repository'),
            service(Connection::class),
        ])
        ->tag('shopware.payment.method');

    $services->set(AppRegistrationService::class)
        ->args([
            service(HandshakeFactory::class),
            service('shopware.app_system.guzzle'),
            service('app.repository'),
            env('APP_URL'),
            service(ShopIdProvider::class),
            param('kernel.shopware_version'),
            service(ClockInterface::class),
            service('logger'),
        ]);

    $services->set(AppSecretRotationService::class)
        ->args([
            service(AppRegistrationService::class),
            service('app.repository'),
            service('integration.repository'),
            service('messenger.default_bus'),
            service('logger'),
            service(ManifestFactory::class),
            service(ClockInterface::class),
            service(DeletedAppsGateway::class),
        ]);

    $services->set(AppFeatureValidator::class)
        ->args([
            param('kernel.environment'),
        ]);

    $services->set(AppStorage::class)
        ->args([
            service('app.repository'),
        ]);

    $services->set(HandshakeFactory::class)
        ->args([
            env('APP_URL'),
            service(ShopIdProvider::class),
            service(StoreClient::class),
            param('kernel.shopware_version'),
            service(ClockInterface::class),
        ]);

    $services->set(AppManager::class)
        ->args([
            tagged_iterator('shopware.app_lifecycle.handler'),
            service('app.repository'),
            service(PermissionLifecycleService::class),
            service('event_dispatcher'),
            service(AppRegistrationService::class),
            service(AppSecretRotationService::class),
            service(ManifestFactory::class),
            service(ActiveAppsLoader::class),
            service('language.repository'),
            service(SystemConfigService::class),
            service(ConfigValidator::class),
            service('integration.repository'),
            service('acl_role.repository'),
            service(AssetService::class),
            service(ScriptExecutor::class),
            param('kernel.project_dir'),
            service(CustomEntityLifecycleService::class),
            param('kernel.shopware_version'),
            service(AppFeatureValidator::class),
            service(SourceResolver::class),
            service(ConfigReader::class),
            service(DeletedAppsGateway::class),
            service(AppRequirementsValidator::class),
            service(ClockInterface::class),
        ]);

    $services->set(DiscardUnconfirmedAppSecretsListener::class)
        ->args([
            service('app.repository'),
        ])
        ->tag('kernel.event_listener');

    $services->set(AppLifecycle::class)
        ->args([
            service(AppManager::class),
            service(AppStorage::class),
        ]);

    $services->set(AppLifecycleIterator::class)
        ->args([
            service('app.repository'),
            service(AppLoader::class),
        ]);

    $services->set(AbstractAppUpdater::class, AppUpdater::class)
        ->args([
            service(AbstractExtensionDataProvider::class),
            service('app.repository'),
            service(ExtensionDownloader::class),
            service(AbstractStoreAppLifecycleService::class),
        ]);

    $services->set(UpdateAppsTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(UpdateAppsHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(AbstractAppUpdater::class)->nullOnInvalid(),
        ])
        ->tag('messenger.message_handler');

    $services->set(DeleteCascadeAppsTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(DeleteCascadeAppsHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('acl_role.repository'),
            service('integration.repository'),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(RotateAppSecretHandler::class)
        ->args([
            service(AppSecretRotationService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(AppLoader::class)
        ->args([
            param('shopware.app_dir'),
            service('logger'),
        ]);

    $services->set('shopware.app_system.guzzle.middleware', AuthMiddleware::class)
        ->args([
            param('kernel.shopware_version'),
            service(AppLocaleProvider::class),
        ]);

    $services->set('shopware.app_system.trusted_url_resolver', TrustedUrlResolver::class)
        ->args([
            null,
            true,
            param('shopware.app_system.allowed_private_ip_addresses'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('shopware.app_system.guzzle.security_middleware', AppSystemHttpMiddleware::class)
        ->args([
            service('shopware.app_system.trusted_url_resolver'),
            param('shopware.app_system.allow_unencrypted_traffic'),
        ]);

    $services->set('shopware.app_system.guzzle', Client::class)
        ->lazy()
        ->args([
            [
                'timeout' => 5,
                'connect_timeout' => 1,
                'proxy' => [],
                'handler' => inline_service(HandlerStack::class)
                    ->factory([HandlerStack::class, 'create'])
                    ->call('after', [
                        'allow_redirects',
                        service('shopware.app_system.guzzle.security_middleware'),
                        'app_system_http_security',
                    ])
                    ->call('push', [
                        service('shopware.app_system.guzzle.middleware'),
                    ]),
            ],
        ]);

    $services->set(ActionButtonLoader::class)
        ->args([
            service('app_action_button.repository'),
        ]);

    $services->set(ActionButtonResponseFactory::class)
        ->args([
            tagged_iterator('shopware.action_button.response_factory'),
        ]);

    $services->set(NotificationResponseFactory::class)
        ->tag('shopware.action_button.response_factory');

    $services->set(OpenModalResponseFactory::class)
        ->args([
            service(QuerySigner::class),
        ])
        ->tag('shopware.action_button.response_factory');

    $services->set(OpenNewTabResponseFactory::class)
        ->args([
            service(QuerySigner::class),
        ])
        ->tag('shopware.action_button.response_factory');

    $services->set(ReloadDataResponseFactory::class)
        ->tag('shopware.action_button.response_factory');

    $services->set(QuerySigner::class)
        ->args([
            env('APP_URL'),
            param('kernel.shopware_version'),
            service(LocaleProvider::class),
            service(ShopIdProvider::class),
            service(InAppPurchase::class),
            service(ClockInterface::class),
        ]);

    $services->set(Executor::class)
        ->args([
            service('shopware.app_system.guzzle'),
            service('logger'),
            service(ActionButtonResponseFactory::class),
            service(ShopIdProvider::class),
            service('router'),
            service('request_stack'),
            service('kernel'),
            service(ClockInterface::class),
        ]);

    $services->set(AppActionLoader::class)
        ->args([
            service('app_action_button.repository'),
            service(AppPayloadServiceHelper::class),
        ]);

    $services->set(AppActionController::class)
        ->public()
        ->args([
            service(ActionButtonLoader::class),
            service(AppActionLoader::class),
            service(Executor::class),
            service(ModuleLoader::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(AppCmsController::class)
        ->public()
        ->args([
            service('app_cms_block.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(AppJWTGenerateRoute::class)
        ->public()
        ->args([
            service(Connection::class),
            service(ShopIdProvider::class),
            service(InAppPurchase::class),
            service(ClockInterface::class),
        ]);

    $services->set(AppSecretRotationController::class)
        ->public()
        ->args([
            service('app.repository'),
            service(AppSecretRotationService::class),
        ]);

    $services->set(AppPrinter::class)
        ->args([
            service('app.repository'),
        ]);

    $services->set(AppLocaleProvider::class)
        ->public()
        ->args([
            service('user.repository'),
            service(LanguageLocaleCodeProvider::class),
        ]);

    // COMMANDS
    $services->set(RefreshAppCommand::class)
        ->args([
            service(AppService::class),
            service(AppPrinter::class),
            service(ManifestValidator::class),
        ])
        ->tag('console.command');

    $services->set(InstallAppCommand::class)
        ->args([
            service(AppLoader::class),
            service(AppLifecycle::class),
            service(AppPrinter::class),
            service(ManifestValidator::class),
        ])
        ->tag('console.command');

    $services->set(UninstallAppCommand::class)
        ->args([
            service(AppLifecycle::class),
            service(AppStorage::class),
        ])
        ->tag('console.command');

    $services->set(ActivateAppCommand::class)
        ->args([
            service(AppStorage::class),
            service(AppLifecycle::class),
        ])
        ->tag('console.command');

    $services->set(DeactivateAppCommand::class)
        ->args([
            service(AppStorage::class),
            service(AppLifecycle::class),
        ])
        ->tag('console.command');

    $services->set(CreateAppCommand::class)
        ->args([
            service(AppLifecycle::class),
            param('shopware.app_dir'),
        ])
        ->tag('console.command');

    $services->set(ValidateAppCommand::class)
        ->args([
            param('shopware.app_dir'),
            service(ManifestValidator::class),
        ])
        ->tag('console.command');

    $services->set(ChangeShopIdCommand::class)
        ->args([
            service(Resolver::class),
        ])
        ->tag('console.command');

    $services->set(AppListCommand::class)
        ->args([
            service(AppStorage::class),
        ])
        ->tag('console.command');

    $services->set(RotateAppSecretCommand::class)
        ->args([
            service('app.repository'),
            service(AppSecretRotationService::class),
            service(ActiveAppsLoader::class),
        ])
        ->tag('console.command');

    $services->set(ShopIdController::class)
        ->public()
        ->args([
            service(Resolver::class),
            service(ShopIdProvider::class),
            service('app.repository'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(Resolver::class)
        ->public()
        ->args([
            tagged_iterator('shopware.app_url_changed_resolver'),
        ]);

    $services->set(MoveShopPermanentlyStrategy::class)
        ->args([
            service('app.repository'),
            service(AppManager::class),
            service(ShopIdProvider::class),
            service('logger'),
        ])
        ->tag('shopware.app_url_changed_resolver', ['priority' => -100]);

    $services->set(ReinstallAppsStrategy::class)
        ->args([
            service('app.repository'),
            service(AppManager::class),
            service(ShopIdProvider::class),
            service('logger'),
        ])
        ->tag('shopware.app_url_changed_resolver', ['priority' => 100]);

    $services->set(UninstallAppsStrategy::class)
        ->args([
            service('app.repository'),
            service(ShopIdProvider::class),
            service(AppManager::class),
        ])
        ->tag('shopware.app_url_changed_resolver', ['priority' => 0]);

    // DELTA
    $services->set(PermissionsDeltaProvider::class)
        ->tag('shopware.app_delta');

    $services->set(DomainsDeltaProvider::class)
        ->tag('shopware.app_delta');

    // ENTITY DEFINITIONS
    $services->set(AppDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ActionButtonDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ActionButtonTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(TemplateDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppPaymentMethodDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppScriptConditionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppScriptConditionTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppCmsBlockDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppCmsBlockTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppFlowActionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppFlowActionTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppFlowEventDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppShippingMethodDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppFlowActionLoadedSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(BlockTemplateLoader::class);

    $services->set(AppFlowActionProvider::class)
        ->public()
        ->args([
            service(Connection::class),
            service(BusinessEventEncoder::class),
            service(StringTemplateRenderer::class),
        ]);

    $services->set(AppConfirmationDeltaProvider::class)
        ->args([
            tagged_iterator('shopware.app_delta'),
        ]);

    $services->set(NoDatabaseSourceResolver::class)
        ->args([
            service(ActiveAppsLoader::class),
        ]);

    $services->set(SourceResolver::class)
        ->args([
            tagged_iterator('app.source_resolver'),
            service('app.repository'),
            service(NoDatabaseSourceResolver::class),
        ]);

    $services->set(RemoteZip::class)
        ->args([
            service(TemporaryDirectoryFactory::class),
            service(AppDownloader::class),
            service(AppExtractor::class),
        ])
        ->tag('app.source_resolver');

    $services->set(Local::class)
        ->args([
            param('kernel.project_dir'),
        ])
        ->tag('app.source_resolver', ['priority' => -100]);

    $services->set(AppArchiveValidator::class);

    $services->set(AppExtractor::class)
        ->args([
            service(AppArchiveValidator::class),
        ]);

    $services->set(AppDownloader::class)
        ->args([
            service(HttpClientInterface::class),
        ]);

    $services->set(TemporaryDirectoryFactory::class);

    $services->set(AppTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopware.telemetry.subscriber');

    $services->set(Privileges::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(AppCapability::class)
        ->args([
            service(Privileges::class),
        ]);

    $services->set(AppPrivilegeController::class)
        ->public()
        ->args([
            service(Connection::class),
            service(Privileges::class),
        ]);

    $services->set(SalesChannelDomainUrls::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.app_system.shop_id_fingerprint');

    $services->set(InstallationPath::class)
        ->args([
            param('kernel.project_dir'),
        ])
        ->tag('shopware.app_system.shop_id_fingerprint');

    $services->set(AppUrl::class)
        ->tag('shopware.app_system.shop_id_fingerprint');

    $services->set(FingerprintGenerator::class)
        ->args([
            tagged_iterator('shopware.app_system.shop_id_fingerprint'),
        ]);

    $services->set(CheckShopIdCommand::class)
        ->args([
            service(SystemConfigService::class),
            service(FingerprintGenerator::class),
        ])
        ->tag('console.command');

    $services->set(SystemHeartbeatTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(SystemHeartbeatHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service('event_dispatcher'),
        ])
        ->tag('messenger.message_handler');

    $services->set(DeletedAppsGateway::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(AppSecretResolver::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(RememberDeletedAppsSecretSubscriber::class)
        ->args([
            service('app.repository'),
            service(DeletedAppsGateway::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AppUrlVerifier::class)
        ->args([
            param('kernel.environment'),
            param('kernel.shopware_version'),
            service('cache.app'),
            service(HttpClientInterface::class),
            service(LockManager::class),
            service('logger'),
            service(ClockInterface::class),
        ]);

    $services->set(AppUrlVerificationPrinter::class)
        ->args([
            service(ShopIdProvider::class),
        ]);

    $services->set(AppUrlVerificationStatusCommand::class)
        ->args([
            service(AppUrlVerifier::class),
            service(AppUrlVerificationPrinter::class),
        ])
        ->tag('console.command');

    $services->set(AppUrlVerifyCommand::class)
        ->args([
            service(ShopIdProvider::class),
            service(AppUrlVerifier::class),
            service(AppUrlVerificationPrinter::class),
        ])
        ->tag('console.command');

    $services->set(VerifyShopController::class)
        ->public()
        ->args([
            service('shopware.rate_limiter'),
            service(AppUrlVerifier::class),
        ]);
};
