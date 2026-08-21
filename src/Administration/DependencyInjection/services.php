<?php declare(strict_types=1);

namespace Shopware\Administration\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Administration\Command\CheckExtensionsCommand;
use Shopware\Administration\Command\DeleteAdminFilesAfterBuildCommand;
use Shopware\Administration\Command\DeleteExtensionLocalPublicFilesCommand;
use Shopware\Administration\Command\GenerateEntitySchemaTypesCommand;
use Shopware\Administration\Command\SetupExtensionToolingCommand;
use Shopware\Administration\Controller\AdminExtensionApiController;
use Shopware\Administration\Controller\AdministrationController;
use Shopware\Administration\Controller\AdminProductStreamController;
use Shopware\Administration\Controller\AdminSearchController;
use Shopware\Administration\Controller\AdminTagController;
use Shopware\Administration\Controller\DashboardController;
use Shopware\Administration\Controller\NotificationController;
use Shopware\Administration\Controller\UserConfigController;
use Shopware\Administration\Dashboard\OrderAmountService;
use Shopware\Administration\Framework\Adapter\Cache\Http\AdministrationCacheControlListener;
use Shopware\Administration\Framework\Routing\KnownIps\KnownIpsCollector;
use Shopware\Administration\Notification\NotificationDefinition;
use Shopware\Administration\Service\AdminSearcher;
use Shopware\Administration\Snippet\AppAdministrationSnippetDefinition;
use Shopware\Administration\Snippet\AppAdministrationSnippetPersister;
use Shopware\Administration\Snippet\AppLifecycleSubscriber;
use Shopware\Administration\Snippet\CachedSnippetFinder;
use Shopware\Administration\Snippet\SnippetFinder;
use Shopware\Administration\System\SalesChannel\Subscriber\SalesChannelUserConfigSubscriber;
use Shopware\Core\Checkout\Cart\Price\CashRounding;
use Shopware\Core\Checkout\Customer\Validation\CustomerEmailUniqueChecker;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\Http\Event\BeforeCacheControlEvent;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\Hmac\QuerySigner;
use Shopware\Core\Framework\App\Payload\AppPayloadServiceHelper;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Store\Services\FirstRunWizardService;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Core\System\Tag\Service\FilterTagIdsService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('env(SHOPWARE_ADMINISTRATION_PATH_NAME)', 'admin')
        ->set('shopware_administration.path_name', env('SHOPWARE_ADMINISTRATION_PATH_NAME')->resolve());

    $services = $containerConfigurator->services();

    $services->set(DeleteAdminFilesAfterBuildCommand::class)
        ->args([
            service(Filesystem::class),
        ])
        ->tag('console.command');

    $services->set(DeleteExtensionLocalPublicFilesCommand::class)
        ->args([
            service('kernel'),
        ])
        ->tag('console.command');

    $services->set(CheckExtensionsCommand::class)
        ->args([
            service('kernel'),
        ])
        ->tag('console.command');

    $services->set(SetupExtensionToolingCommand::class)
        ->args([
            service('kernel'),
        ])
        ->tag('console.command');

    $services->set(GenerateEntitySchemaTypesCommand::class)
        ->tag('console.command');

    $services->set(AdminExtensionApiController::class)
        ->public()
        ->args([
            service(Executor::class),
            service(AppPayloadServiceHelper::class),
            service('app.repository'),
            service(QuerySigner::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdministrationController::class)
        ->public()
        ->args([
            service(TemplateFinder::class),
            service(FirstRunWizardService::class),
            service(SnippetFinder::class),
            param('kernel.supported_api_versions'),
            service(KnownIpsCollector::class),
            service(Connection::class),
            service('event_dispatcher'),
            param('kernel.shopware_core_dir'),
            service('customer.repository'),
            service('currency.repository'),
            service(HtmlSanitizer::class),
            service(DefinitionInstanceRegistry::class),
            service('parameter_bag'),
            service('shopware.filesystem.asset'),
            param('shopware.service_registry.url'),
            service('language.repository'),
            service(SymfonyBearerTokenValidator::class),
            env('PRODUCT_ANALYTICS_GATEWAY_URL'),
            service(CustomerEmailUniqueChecker::class),
            param('shopware.api.refresh_token_ttl'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminSearchController::class)
        ->public()
        ->args([
            service(RequestCriteriaBuilder::class),
            service(DefinitionInstanceRegistry::class),
            service(AdminSearcher::class),
            service('serializer'),
            service(AclCriteriaValidator::class),
            service(DefinitionInstanceRegistry::class),
            service(JsonEntityEncoder::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(UserConfigController::class)
        ->public()
        ->args([
            service('user_config.repository'),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminProductStreamController::class)
        ->public()
        ->args([
            service(ProductDefinition::class),
            service('sales_channel.product.repository'),
            service(SalesChannelContextService::class),
            service(RequestCriteriaBuilder::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminTagController::class)
        ->public()
        ->args([
            service(FilterTagIdsService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(NotificationController::class)
        ->public()
        ->args([
            service('shopware.rate_limiter'),
            service(NotificationService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdminSearcher::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(AppAdministrationSnippetDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppAdministrationSnippetPersister::class)
        ->args([
            service('app_administration_snippet.repository'),
            service('locale.repository'),
            service(CacheInvalidator::class),
            service(Filesystem::class),
        ]);

    $services->set(SnippetFinder::class)
        ->args([
            service('kernel'),
            service(Connection::class),
            service('shopware.filesystem.translation'),
            service(TranslationConfig::class),
            service(TranslationLoader::class),
            service(HtmlSanitizer::class),
            service('logger'),
            param('kernel.debug'),
        ]);

    $services->set(CachedSnippetFinder::class)
        ->decorate(SnippetFinder::class)
        ->args([
            service(CachedSnippetFinder::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(AppLifecycleSubscriber::class)
        ->args([
            service(SourceResolver::class),
            service(AppAdministrationSnippetPersister::class),
        ])
        ->tag('kernel.event_subscriber');

    // @deprecated tag:v6.8.0 Will be removed
    $services->set(NotificationDefinition::class)
        ->deprecate('shopware/administration', '6.8.0', '');

    $services->set(SalesChannelUserConfigSubscriber::class)
        ->args([
            service('user_config.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(OrderAmountService::class)
        ->args([
            service(Connection::class),
            service(CashRounding::class),
            param('shopware.dbal.time_zone_support_enabled'),
        ]);

    $services->set(DashboardController::class)
        ->public()
        ->args([
            service(OrderAmountService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AdministrationCacheControlListener::class)
        ->tag('kernel.event_listener', ['event' => BeforeCacheControlEvent::class]);
};
