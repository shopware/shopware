<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\Slugify;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\ApiOrderCartService;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStateValidator;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStore;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Adapter\Command\S3FilesystemVisibilityCommand;
use Shopware\Core\Framework\Adapter\Kernel\EnvIntOrNullProcessor;
use Shopware\Core\Framework\Adapter\Kernel\HttpCacheKernel;
use Shopware\Core\Framework\Adapter\Kernel\HttpKernel;
use Shopware\Core\Framework\Adapter\Lock\LockManager;
use Shopware\Core\Framework\Adapter\Redis\RedisConnectionProvider;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Shopware\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Adapter\Twig\AppTemplateIterator;
use Shopware\Core\Framework\Adapter\Twig\BackwardCompatibleIntlExtension;
use Shopware\Core\Framework\Adapter\Twig\EntityTemplateLoader;
use Shopware\Core\Framework\Adapter\Twig\Extension\ComparisonExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\ConfigExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\InAppPurchaseExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\InstanceOfExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\PcreExtension;
use Shopware\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Shopware\Core\Framework\Adapter\Twig\Filter\CurrencyFilter;
use Shopware\Core\Framework\Adapter\Twig\Filter\EmailIdnTwigFilter;
use Shopware\Core\Framework\Adapter\Twig\Filter\LeadingSpacesFilter;
use Shopware\Core\Framework\Adapter\Twig\Filter\ReplaceRecursiveFilter;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\SecurityExtension;
use Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateIterator;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Api\Controller\SalesChannelProxyController;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Demodata\PersonalData\CleanPersonalDataCommand;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Feature\Command\FeatureDisableCommand;
use Shopware\Core\Framework\Feature\Command\FeatureDumpCommand;
use Shopware\Core\Framework\Feature\Command\FeatureEnableCommand;
use Shopware\Core\Framework\Feature\Command\FeatureListCommand;
use Shopware\Core\Framework\Feature\FeatureFlagRegistry;
use Shopware\Core\Framework\Log\ExceptionLogger;
use Shopware\Core\Framework\Log\LogEntryDefinition;
use Shopware\Core\Framework\Log\LoggingService;
use Shopware\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Shopware\Core\Framework\Log\Monolog\ErrorCodeLogLevelHandler;
use Shopware\Core\Framework\Log\Monolog\ExcludeExceptionHandler;
use Shopware\Core\Framework\Log\Monolog\ExcludeFlowEventHandler;
use Shopware\Core\Framework\Log\ScheduledTask\LogCleanupTask;
use Shopware\Core\Framework\Log\ScheduledTask\LogCleanupTaskHandler;
use Shopware\Core\Framework\Migration\Command\CreateMigrationCommand;
use Shopware\Core\Framework\Migration\Command\MigrationCommand;
use Shopware\Core\Framework\Migration\Command\MigrationDestructiveCommand;
use Shopware\Core\Framework\Migration\Command\RefreshMigrationCommand;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationInfo;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Routing\Annotation\CriteriaValueResolver;
use Shopware\Core\Framework\Routing\ApiRequestContextResolver;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\CanonicalRedirectService;
use Shopware\Core\Framework\Routing\ContextResolverListener;
use Shopware\Core\Framework\Routing\CoreSubscriber;
use Shopware\Core\Framework\Routing\Facade\RequestFacadeFactory;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\Framework\Routing\PaymentScopeWhitelist;
use Shopware\Core\Framework\Routing\QueryDataBagResolver;
use Shopware\Core\Framework\Routing\RequestDataBagResolver;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Routing\RouteEventSubscriber;
use Shopware\Core\Framework\Routing\RouteParamsCleanupListener;
use Shopware\Core\Framework\Routing\RouteScope;
use Shopware\Core\Framework\Routing\RouteScopeListener;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Routing\SymfonyRouteScopeWhitelist;
use Shopware\Core\Framework\Routing\Telemetry\AreaResolver;
use Shopware\Core\Framework\Routing\Telemetry\DomainResolver;
use Shopware\Core\Framework\Routing\Telemetry\HttpRequestMetricSubscriber;
use Shopware\Core\Framework\Routing\Telemetry\OperationResolver;
use Shopware\Core\Framework\Routing\Validation\Constraint\RouteNotBlockedValidator;
use Shopware\Core\Framework\Routing\Validation\RouteBlocklistService;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Telemetry\Telemetry;
use Shopware\Core\Framework\Util\Backtrace\BacktraceCollector;
use Shopware\Core\Framework\Util\HtmlPurifierConfigProvider;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Kernel;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\CustomEntity\CustomEntityLifecycleService;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\AdminUiXmlSchemaValidator;
use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityEnrichmentService;
use Shopware\Core\System\CustomEntity\Xml\CustomEntityXmlSchemaValidator;
use Shopware\Core\System\Locale\LanguageLocaleCodeProvider;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\Snippet\Api\SnippetController;
use Shopware\Core\System\Snippet\Api\TranslationController;
use Shopware\Core\System\Snippet\Files\AppSnippetFileLoader;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Files\SnippetFileCollectionFactory;
use Shopware\Core\System\Snippet\Files\SnippetFileLoader;
use Shopware\Core\System\Snippet\Filter\AddedFilter;
use Shopware\Core\System\Snippet\Filter\AuthorFilter;
use Shopware\Core\System\Snippet\Filter\EditedFilter;
use Shopware\Core\System\Snippet\Filter\EmptySnippetFilter;
use Shopware\Core\System\Snippet\Filter\NamespaceFilter;
use Shopware\Core\System\Snippet\Filter\SnippetFilterFactory;
use Shopware\Core\System\Snippet\Filter\TermFilter;
use Shopware\Core\System\Snippet\Filter\TranslationKeyFilter;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\Service\TranslationMetadataStore;
use Shopware\Core\System\Snippet\Service\TranslationRemover;
use Shopware\Core\System\Snippet\Service\TranslationUpdater;
use Shopware\Core\System\Snippet\SnippetService;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\Runner\Symfony\HttpKernelRunner;
use Symfony\Component\Runtime\Runner\Symfony\ResponseRunner;
use Symfony\Component\Runtime\SymfonyRuntime;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extra\String\StringExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('shopware.slug.config', [
        'regexp' => '/([^A-Za-z0-9\.]|-)+/',
        'lowercase' => false,
    ]);

    // Populated by RouteScopeCompilerPass with all route prefixes from the registers RouteScopes
    $parameters->set('shopware.routing.registered_api_prefixes', []);

    // Migration config
    $parameters->set('core.migration.directories', []);

    $parameters->set('shopware.security.csp_templates', [
        'default' => "\nobject-src 'none';\nscript-src 'none';\nbase-uri 'self';\nframe-ancestors 'none';\n            ",
        'administration' => "\nobject-src 'none';\nscript-src 'strict-dynamic' 'nonce-%%nonce%%' 'unsafe-inline' 'unsafe-eval' https: http:;\nbase-uri 'self';\nframe-ancestors 'none';\n            ",
        'storefront' => '',
        'installer' => '',
    ]);

    $parameters->set('shopware_http_cache_enabled_default', 1);
    $parameters->set('shopware.http.cache.enabled', env('SHOPWARE_HTTP_CACHE_ENABLED')->default('shopware_http_cache_enabled_default'));

    // @deprecated tag:v6.8.0 Will be removed
    $parameters->set('shopware_http_cache_default_ttl_default', 7200);
    // @deprecated tag:v6.8.0 Will be removed
    $parameters->set('shopware.http.cache.default_ttl', env('SHOPWARE_HTTP_DEFAULT_TTL')->default('shopware_http_cache_default_ttl_default'));

    $containerConfigurator->extension('monolog', [
        'channels' => ['business_events'],
        'handlers' => [
            'business_event_handler_buffer' => [
                'type' => 'buffer',
                'handler' => 'business_event_handler',
                'channels' => ['business_events'],
            ],
            'business_event_handler' => [
                'type' => 'service',
                'id' => DoctrineSQLHandler::class,
                'channels' => ['business_events'],
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    // Database / Doctrine
    $services->set(Connection::class)
        ->public()
        ->factory([Kernel::class, 'getConnection']);

    $services->set(QueryDataBagResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1000]);

    $services->set(RequestDataBagResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1000]);

    $services->set(LockManager::class)
        ->args([
            service('lock.factory'),
        ]);

    // Cache
    $services->set('slugify', Slugify::class)
        ->args([
            param('shopware.slug.config'),
        ]);

    // Migration
    $services->set(MigrationSource::class . '.core', MigrationSource::class)
        ->args([
            'core',
        ])
        ->tag('shopware.migration_source');

    $services->set(MigrationSource::class . '.core.V6_3', MigrationSource::class)
        ->args([
            'core.V6_3',
        ])
        ->tag('shopware.migration_source');

    $services->set(MigrationSource::class . '.core.V6_4', MigrationSource::class)
        ->args([
            'core.V6_4',
        ])
        ->tag('shopware.migration_source');

    $services->set(MigrationSource::class . '.core.V6_5', MigrationSource::class)
        ->args([
            'core.V6_5',
        ])
        ->tag('shopware.migration_source');

    $services->set(MigrationSource::class . '.core.V6_6', MigrationSource::class)
        ->args([
            'core.V6_6',
        ])
        ->tag('shopware.migration_source');

    $services->set(MigrationSource::class . '.core.V6_7', MigrationSource::class)
        ->args([
            'core.V6_7',
        ])
        ->tag('shopware.migration_source');

    $services->set(MigrationSource::class . '.core.V6_8', MigrationSource::class)
        ->args([
            'core.V6_8',
        ])
        ->tag('shopware.migration_source');

    $services->set(MigrationSource::class . '.null', MigrationSource::class)
        ->args([
            'null',
            [],
        ])
        ->tag('shopware.migration_source');

    $services->set(MigrationRuntime::class)
        ->args([
            service(Connection::class),
            service('logger'),
        ]);

    $services->set(MigrationCollectionLoader::class)
        ->public()
        ->args([
            service(Connection::class),
            service(MigrationRuntime::class),
            service('logger'),
            tagged_iterator('shopware.migration_source'),
        ]);

    $services->set(MigrationInfo::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CreateMigrationCommand::class)
        ->args([
            service(KernelPluginCollection::class),
            param('kernel.shopware_core_dir'),
            param('kernel.shopware_version'),
        ])
        ->tag('console.command');

    $services->set(RefreshMigrationCommand::class)
        ->args([
            service('filesystem'),
            service(ClockInterface::class),
        ])
        ->tag('console.command');

    $services->set(MigrationCommand::class)
        ->args([
            service(MigrationCollectionLoader::class),
            service('cache.object'),
            param('kernel.shopware_version'),
        ])
        ->tag('console.command');

    $services->set(CleanPersonalDataCommand::class)
        ->args([
            service(Connection::class),
            service('customer.repository'),
            service(ClockInterface::class),
        ])
        ->tag('console.command');

    $services->set(MigrationDestructiveCommand::class)
        ->args([
            service(MigrationCollectionLoader::class),
            service('cache.object'),
            param('kernel.shopware_version'),
        ])
        ->tag('console.command');

    $services->set(IndexerQueuer::class)
        ->public()
        ->args([
            service(Connection::class),
        ]);

    // Serializer
    $services->set(StructNormalizer::class)
        ->tag('serializer.normalizer');

    // Routing
    $services->set(ContextResolverListener::class)
        ->args([
            service(ApiRequestContextResolver::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CoreSubscriber::class)
        ->args([
            param('shopware.security.csp_templates'),
            service(ScriptExecutor::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SymfonyRouteScopeWhitelist::class)
        ->tag('shopware.route_scope_whitelist');

    $services->set(PaymentScopeWhitelist::class)
        ->tag('shopware.route_scope_whitelist');

    $services->set(RouteScopeListener::class)
        ->args([
            service(RouteScopeRegistry::class),
            service('request_stack'),
            tagged_iterator('shopware.route_scope_whitelist'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CanonicalRedirectService::class)
        ->public()
        ->args([
            service(SystemConfigService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(RouteEventSubscriber::class)
        ->tag('kernel.event_subscriber')
        ->args([
            service('event_dispatcher'),
        ]);

    $services->set(MaintenanceModeResolver::class)
        ->args([
            service('event_dispatcher'),
        ]);

    // Telemetry: per-main-request HTTP metrics and their label resolvers
    $services->set(AreaResolver::class);

    $services->set(DomainResolver::class)
        ->args([
            service(EntityGroupResolver::class),
        ]);

    $services->set(OperationResolver::class);

    $services->set(HttpRequestMetricSubscriber::class)
        ->args([
            service(Telemetry::class),
            service(AreaResolver::class),
            service(DomainResolver::class),
            service(OperationResolver::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopware.telemetry.subscriber');

    $services->set(RouteBlocklistService::class)
        ->args([
            service('router'),
        ]);

    $services->set(RouteNotBlockedValidator::class)
        ->args([
            service(RouteBlocklistService::class),
        ])
        ->tag('validator.constraint_validator');

    // Custom Entity
    $services->set(CustomEntityEnrichmentService::class)
        ->args([
            service(AdminUiXmlSchemaValidator::class),
        ]);

    $services->set(CustomEntityLifecycleService::class)
        ->args([
            service(CustomEntityPersister::class),
            service(CustomEntitySchemaUpdater::class),
            service(CustomEntityEnrichmentService::class),
            service(CustomEntityXmlSchemaValidator::class),
            service(SourceResolver::class),
            service(Connection::class),
            service('custom_entity.repository'),
            service(ClockInterface::class),
        ]);

    // Translation
    $services->set(Translator::class)
        ->decorate('translator')
        ->args([
            service(Translator::class . '.inner'),
            service('request_stack'),
            service('cache.object'),
            service('translator.formatter'),
            param('kernel.environment'),
            service(Connection::class),
            service(LanguageLocaleCodeProvider::class),
            service(SnippetService::class),
            service(CacheTagCollector::class),
        ])
        ->tag('monolog.logger');

    $services->set(ConstraintViolationTranslator::class)
        ->args([
            service('translator'),
        ]);

    // Snippets
    $services->set(SnippetService::class)
        ->lazy()
        ->args([
            service(Connection::class),
            service(SnippetFileCollection::class),
            service('snippet.repository'),
            service('snippet_set.repository'),
            service(SnippetFilterFactory::class),
            service(ExtensionDispatcher::class),
            service('event_dispatcher'),
            service('shopware.filesystem.translation'),
            service('filesystem'),
        ]);

    $services->set(SnippetController::class)
        ->public()
        ->args([
            service(SnippetService::class),
            service(SnippetFileCollection::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(TranslationController::class)
        ->public()
        ->args([
            service(TranslationConfig::class),
            service(TranslationMetadataStore::class),
            service(TranslationUpdater::class),
            service(TranslationRemover::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SnippetFileLoader::class)
        ->args([
            service(KernelInterface::class),
            service(Connection::class),
            service(AppSnippetFileLoader::class),
            service(ActiveAppsLoader::class),
            service(TranslationConfig::class),
            service(TranslationLoader::class),
            service('shopware.filesystem.translation'),
            service(SourceResolver::class),
            service('logger'),
        ]);

    $services->set(AppSnippetFileLoader::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(SnippetFileCollection::class)
        ->public()
        ->lazy()
        ->factory([service(SnippetFileCollectionFactory::class), 'createSnippetFileCollection']);

    $services->set(SnippetFileCollectionFactory::class)
        ->args([
            service(SnippetFileLoader::class),
        ]);

    $services->set(SnippetFilterFactory::class)
        ->public()
        ->args([
            tagged_iterator('shopware.snippet.filter'),
        ]);

    // SnippetFilters
    $services->set(AuthorFilter::class)
        ->tag('shopware.snippet.filter');

    $services->set(AddedFilter::class)
        ->tag('shopware.snippet.filter');

    $services->set(EditedFilter::class)
        ->tag('shopware.snippet.filter');

    $services->set(EmptySnippetFilter::class)
        ->tag('shopware.snippet.filter');

    $services->set(NamespaceFilter::class)
        ->tag('shopware.snippet.filter');

    $services->set(TermFilter::class)
        ->tag('shopware.snippet.filter');

    $services->set(TranslationKeyFilter::class)
        ->tag('shopware.snippet.filter');

    // Twig
    $services->set(TemplateFinder::class)
        ->public()
        ->args([
            service('twig'),
            service('twig.loader'),
            param('twig.cache'),
            service(NamespaceHierarchyBuilder::class),
            service(TemplateScopeDetector::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(NamespaceHierarchyBuilder::class)
        ->args([
            tagged_iterator('shopware.twig.hierarchy_builder'),
        ]);

    $services->set(BundleHierarchyBuilder::class)
        ->args([
            service('kernel'),
            service(Connection::class),
        ])
        ->tag('shopware.twig.hierarchy_builder', ['priority' => 1000]);

    $services->set(TemplateScopeDetector::class)
        ->args([
            service('request_stack'),
        ]);

    $services->set(NodeExtension::class)
        ->args([
            service(TemplateFinder::class),
            service(TemplateScopeDetector::class),
        ])
        ->tag('twig.extension');

    $services->set(PhpSyntaxExtension::class)
        ->tag('twig.extension')
        ->tag('shopware.seo_url.twig.extension')
        ->tag('shopware.app_script.twig.extension');

    $services->set(FeatureFlagExtension::class)
        ->tag('twig.extension');

    $services->set(ConfigExtension::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('twig.extension');

    $services->set('twig.extension.intl', IntlExtension::class)
        ->tag('twig.extension');

    $services->set('twig.extension.string', StringExtension::class)
        ->tag('twig.extension');

    $services->set('twig.extension.trans', TranslationExtension::class)
        ->args([
            service('translator'),
        ])
        ->tag('twig.extension')
        ->tag('shopware.app_script.twig.extension');

    $services->set(PcreExtension::class)
        ->tag('twig.extension')
        ->tag('shopware.app_script.twig.extension');

    $services->set(InstanceOfExtension::class)
        ->tag('twig.extension');

    $services->set(CurrencyFilter::class)
        ->args([
            service(CurrencyFormatter::class),
        ])
        ->tag('twig.extension');

    $services->set(EmailIdnTwigFilter::class)
        ->tag('twig.extension');

    $services->set(LeadingSpacesFilter::class)
        ->tag('twig.extension');

    $services->set(SlugifyExtension::class)
        ->args([
            service('slugify'),
        ])
        ->tag('twig.extension')
        ->tag('shopware.seo_url.twig.extension');

    $services->set(ReplaceRecursiveFilter::class)
        ->tag('twig.extension')
        ->tag('shopware.app_script.twig.extension');

    $services->set(ComparisonExtension::class)
        ->tag('shopware.app_script.twig.extension');

    $services->set(BackwardCompatibleIntlExtension::class)
        ->args([
            service('twig.extension.intl'),
        ])
        ->tag('twig.extension');

    $services->set(SecurityExtension::class)
        ->args([
            param('shopware.twig.allowed_php_functions'),
        ])
        ->tag('twig.extension')
        ->tag('shopware.seo_url.twig.extension')
        ->tag('shopware.app_script.twig.extension');

    $services->set(InAppPurchaseExtension::class)
        ->args([
            service(InAppPurchase::class),
        ])
        ->tag('twig.extension');

    $services->set(StringTemplateRenderer::class)
        ->args([
            service('twig'),
            param('shopware.cache.twig.string_template_renderer_cache_dir'),
        ]);

    $services->set(TemplateIterator::class)
        ->decorate('twig.template_iterator')
        ->public()
        ->args([
            service(TemplateIterator::class . '.inner'),
            param('kernel.bundles'),
            param('kernel.bundles_metadata'),
        ]);

    $services->set(EntityTemplateLoader::class)
        ->args([
            service(Connection::class),
            param('kernel.environment'),
        ])
        ->tag('twig.loader')
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppTemplateIterator::class)
        ->decorate('twig.template_iterator')
        ->public()
        ->args([
            service(AppTemplateIterator::class . '.inner'),
            service('app_template.repository'),
        ]);

    $services->set(TwigVariableParserFactory::class);

    $services->set(ApiRequestContextResolver::class)
        ->args([
            service(Connection::class),
            service(RouteScopeRegistry::class),
        ]);

    $services->set(SalesChannelRequestContextResolver::class)
        ->decorate(ApiRequestContextResolver::class)
        ->args([
            service(SalesChannelRequestContextResolver::class . '.inner'),
            service(SalesChannelContextService::class),
            service('event_dispatcher'),
            service(RouteScopeRegistry::class),
        ]);

    $services->set(ApiOrderCartService::class)
        ->args([
            service(CartService::class),
            service(SalesChannelContextPersister::class),
        ]);

    $services->set(SalesChannelProxyController::class)
        ->public()
        ->args([
            service('kernel'),
            service('sales_channel.repository'),
            service(DataValidator::class),
            service(SalesChannelContextPersister::class),
            service(SalesChannelContextService::class),
            service('event_dispatcher'),
            service(ApiOrderCartService::class),
            service(CartOrderRoute::class),
            service(CartService::class),
            service('request_stack'),
            service(ImitateCustomerTokenGenerator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(RouteScope::class)
        ->tag('shopware.route_scope');

    $services->set(ApiRouteScope::class)
        ->tag('shopware.route_scope');

    $services->set(StoreApiRouteScope::class)
        ->tag('shopware.route_scope');

    $services->set(RouteScopeRegistry::class)
        ->args([
            tagged_iterator('shopware.route_scope'),
        ]);

    // Logging
    $services->set(LoggingService::class)
        ->args([
            param('kernel.environment'),
            service('monolog.logger.business_events'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ExceptionLogger::class)
        ->args([
            param('kernel.environment'),
            param('shopware.logger.enforce_throw_exception'),
            service('logger'),
        ]);

    $services->set(LogCleanupTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(LogCleanupTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(SystemConfigService::class),
            service(Connection::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(DoctrineSQLHandler::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(LogEntryDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CriteriaValueResolver::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(RequestCriteriaBuilder::class),
        ])
        ->tag('controller.argument_value_resolver');

    $services->set(FeatureDumpCommand::class)
        ->args([
            service('kernel'),
            service(Filesystem::class),
        ])
        ->tag('console.command')
        ->tag('console.command', ['command' => 'administration:dump:features']);

    $services->set(FeatureDisableCommand::class)
        ->args([
            service(FeatureFlagRegistry::class),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(FeatureEnableCommand::class)
        ->args([
            service(FeatureFlagRegistry::class),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(FeatureListCommand::class)
        ->tag('console.command');

    $services->set(S3FilesystemVisibilityCommand::class)
        ->args([
            service('shopware.filesystem.private'),
            service('shopware.filesystem.public'),
            service('shopware.filesystem.theme'),
            service('shopware.filesystem.sitemap'),
            service('shopware.filesystem.asset'),
        ])
        ->tag('console.command');

    $services->set(HtmlPurifierConfigProvider::class);

    $services->set(HtmlSanitizer::class)
        ->public()
        ->args([
            param('shopware.html_sanitizer.cache_dir'),
            param('shopware.html_sanitizer.cache_enabled'),
            param('shopware.html_sanitizer.sets'),
            param('shopware.html_sanitizer.fields'),
            param('shopware.html_sanitizer.enabled'),
            service(HtmlPurifierConfigProvider::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ExcludeExceptionHandler::class)
        ->decorate('monolog.handler.main', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([
            service(ExcludeExceptionHandler::class . '.inner'),
            param('shopware.logger.exclude_exception'),
        ]);

    $services->set(ErrorCodeLogLevelHandler::class)
        ->decorate('monolog.handler.main', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([
            service(ErrorCodeLogLevelHandler::class . '.inner'),
            param('shopware.logger.error_code_log_levels'),
        ]);

    $services->set(ExcludeFlowEventHandler::class)
        ->decorate('monolog.handler.main', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([
            service(ExcludeFlowEventHandler::class . '.inner'),
            param('shopware.logger.exclude_events'),
        ]);

    $services->set(RouteParamsCleanupListener::class)
        ->tag('kernel.event_listener');

    $services->set(RedisConnectionFactory::class)
        ->args([
            param('shopware.cache.redis_prefix'),
        ]);

    $services->set(RedisConnectionProvider::class)
        ->args([
            '', // $serviceLocator will be set in the compiler pass
        ]);

    $services->set(RequestFacadeFactory::class)
        ->public()
        ->args([
            service('request_stack'),
        ]);

    $services->set(AbstractKeyValueStorage::class, MySQLKeyValueStorage::class)
        ->public()
        ->args([
            service(Connection::class),
        ]);

    $services->set('http_kernel', HttpKernel::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service('controller_resolver'),
            service('request_stack'),
            service('argument_resolver'),
            service(RequestTransformerInterface::class),
            service(CanonicalRedirectService::class),
        ])
        ->tag('container.hot_path')
        ->tag('container.preload', ['class' => HttpKernelRunner::class])
        ->tag('container.preload', ['class' => ResponseRunner::class])
        ->tag('container.preload', ['class' => SymfonyRuntime::class]);

    $services->set('http_kernel.cache', HttpCacheKernel::class)
        ->decorate('http_kernel')
        ->args([
            service('http_kernel.cache.inner'),
            service(CacheStore::class),
            service('esi'),
            [],
            service('event_dispatcher'),
            param('shopware.http_cache.reverse_proxy.enabled'),
        ]);

    $services->set(CacheStore::class)
        ->public()
        ->args([
            service('cache.http'),
            service(CacheStateValidator::class),
            service('event_dispatcher'),
            service(HttpCacheKeyGenerator::class),
            service(MaintenanceModeResolver::class),
            param('session.storage.options'),
            service(CacheTagCollector::class),
            param('shopware.http_cache.soft_purge'),
            service('messenger.bus.default'),
            service(ClockInterface::class),
        ]);

    $services->set(HttpCacheKeyGenerator::class)
        ->args([
            param('kernel.cache.hash'),
            service('event_dispatcher'),
            param('shopware.http_cache.ignored_url_parameters'),
        ]);

    $services->set(CacheStateValidator::class)
        ->args([
            param('shopware.cache.invalidation.http_cache'),
        ]);

    $services->set(BacktraceCollector::class);

    $services->set(EnvIntOrNullProcessor::class)
        ->tag('container.env_var_processor');
};
