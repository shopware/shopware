<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\OAuth\ClientRepository;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\Aggregate\AppMcpPrompt\AppMcpPromptDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpPromptTranslation\AppMcpPromptTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpResourceTranslation\AppMcpResourceTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpToolTranslation\AppMcpToolTranslationDefinition;
use Shopware\Core\Framework\App\Lifecycle\Handler\McpLifecycleHandler;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpPromptPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpResourcePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpToolPersister;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistListRequestHandler;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Authentication\McpAuthenticationListener;
use Shopware\Core\Framework\Mcp\Authentication\McpExceptionListener;
use Shopware\Core\Framework\Mcp\Command\DebugMcpCommand;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Context\StoreApiMcpContextProvider;
use Shopware\Core\Framework\Mcp\Controller\IntegrationMcpAllowlistController;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Controller\McpToolListController;
use Shopware\Core\Framework\Mcp\Controller\StoreApiMcpServerController;
use Shopware\Core\Framework\Mcp\Controller\UserMcpAllowlistController;
use Shopware\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPromptLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpResourceLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolLoader;
use Shopware\Core\Framework\Mcp\McpAllowedHostsProvider;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\Notification\AppMcpCapabilityDetector;
use Shopware\Core\Framework\Mcp\Notification\AppMcpCapabilityLifecycleSubscriber;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Shopware\Core\Framework\Mcp\Prompt\ShopwareContextPrompt;
use Shopware\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Shopware\Core\Framework\Mcp\Resource\BusinessEventsResource;
use Shopware\Core\Framework\Mcp\Resource\CurrencyListResource;
use Shopware\Core\Framework\Mcp\Resource\EntityListResource;
use Shopware\Core\Framework\Mcp\Resource\ExtensionsResource;
use Shopware\Core\Framework\Mcp\Resource\FlowActionsResource;
use Shopware\Core\Framework\Mcp\Resource\LanguageListResource;
use Shopware\Core\Framework\Mcp\Resource\SalesChannelListResource;
use Shopware\Core\Framework\Mcp\Resource\StateMachineResource;
use Shopware\Core\Framework\Mcp\Resource\ToolResultResource;
use Shopware\Core\Framework\Mcp\ScheduledTask\McpToolsetSessionCleanupTask;
use Shopware\Core\Framework\Mcp\ScheduledTask\McpToolsetSessionCleanupTaskHandler;
use Shopware\Core\Framework\Mcp\Session\McpSessionCleanupSubscriber;
use Shopware\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Shopware\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\Tool\MediaUploadTool;
use Shopware\Core\Framework\Mcp\Tool\OrderStateTool;
use Shopware\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Shopware\Core\Framework\Mcp\Tool\ToolSearchTool;
use Shopware\Core\Framework\Mcp\Tool\ToolsetEnableTool;
use Shopware\Core\Framework\Mcp\Tool\ToolsetsListTool;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiContextTool;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolSearchTool;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolsetEnableTool;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolsetsListTool;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The bundle dropped the flat "mcp.pagination_limit" parameter when it gained multiple servers:
    // the limit became a per-server builder value. Keep one Shopware-owned parameter as the single
    // source of truth — McpServerBuilderCompilerPass applies it to every server builder, and the
    // allowlist request handlers page with the same number.
    $container->parameters()->set('shopware.mcp.pagination_limit', 50);

    $services->set('shopware.mcp.session_registry_cache', Psr16Cache::class)
        ->args([service('cache.system')]);

    $services->set(McpSessionRegistry::class)
        ->args([
            service('shopware.mcp.session_registry_cache'),
            'shopware.mcp.active_session_ids',
            service('lock.factory'),
        ]);

    $services->set(McpListChangedNotifier::class)
        ->args([
            service('mcp.server.admin.session.store')->nullOnInvalid(),
            service(McpSessionRegistry::class),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpCapabilityDetector::class)
        ->args([service(Connection::class)]);

    $services->set(AppMcpCapabilityLifecycleSubscriber::class)
        ->args([
            service(AppMcpCapabilityDetector::class),
            service(McpListChangedNotifier::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(McpContextProvider::class)
        ->args([service('request_stack')]);

    $services->set(StoreApiMcpContextProvider::class)
        ->args([service('request_stack')]);

    $services->set(McpAllowlistFilter::class);

    $services->set(McpAllowlistProvider::class)
        ->args([
            service(Connection::class),
            service('request_stack'),
            param('shopware.mcp.tool_dependencies'),
        ]);

    $services->set(McpAllowlistListRequestHandler::class)
        ->args([
            service('mcp.server.admin.registry'),
            service(McpAllowlistProvider::class),
            param('shopware.mcp.pagination_limit'),
            param('shopware.mcp.advertised_tools'),
            service(McpToolsetRegistry::class)->nullOnInvalid(),
            service(McpToolsetSessionStorage::class)->nullOnInvalid(),
            service('request_stack'),
        ])
        ->tag('mcp.admin.request_handler');

    $services->set(McpAuthenticationListener::class)
        ->args([
            service(ClientRepository::class),
            service(RateLimiter::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(McpExceptionListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(McpSessionIdValidator::class);

    $services->set(McpRateLimiter::class)
        ->args([service(RateLimiter::class)]);

    $services->set(McpAllowedHostsProvider::class)
        ->args([
            service(Connection::class),
            env('APP_URL'),
        ]);

    $services->set(McpHttpTransportFactory::class)
        ->args([
            service('mcp.psr_http_factory')->nullOnInvalid(),
            service('mcp.psr17_factory')->nullOnInvalid(),
            service('mcp.psr17_factory')->nullOnInvalid(),
            service('mcp.http_foundation_factory')->nullOnInvalid(),
            service(McpAllowedHostsProvider::class),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(McpServerController::class)
        ->public()
        ->args([
            service('mcp.server.admin')->nullOnInvalid(),
            service(McpHttpTransportFactory::class),
            service(McpRateLimiter::class),
            service(McpSessionIdValidator::class),
            service(McpAllowlistProvider::class),
            service('logger'),
            service(McpAllowlistFilter::class),
            service(McpSessionRegistry::class),
            service(McpListChangedNotifier::class),
        ])
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    // Store-api-scoped discovery stack: second instances of the scope-neutral discovery classes,
    // pointed at the store-api registry/params and an isolated session registry (own cache) so
    // enabling an admin toolset never notifies store-api sessions and vice versa.
    $services->set('mcp.store_api.session_registry_cache', Psr16Cache::class)
        ->args([service('cache.system')]);

    // Distinct cache key from the Admin registry so the two endpoints' active-session populations
    // stay isolated even though both wrap the cache.system pool.
    $services->set('mcp.store_api.session_registry', McpSessionRegistry::class)
        ->args([
            service('mcp.store_api.session_registry_cache'),
            'shopware.mcp.store_api.active_session_ids',
            service('lock.factory'),
        ]);

    $services->set('mcp.store_api.list_changed_notifier', McpListChangedNotifier::class)
        ->args([
            service('mcp.server.store_api.session.store')->nullOnInvalid(),
            service('mcp.store_api.session_registry'),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set('mcp.store_api.capability_catalog', McpCapabilityCatalog::class)
        ->args([
            service('mcp.server.store_api.registry')->nullOnInvalid(),
            service(AppMcpPrivilegeProvider::class),
            param('shopware.store_api_mcp.tool_dependencies'),
            param('shopware.store_api_mcp.tool_privileges'),
            param('shopware.store_api_mcp.tool_groups'),
        ]);

    $services->set('mcp.store_api.toolset_registry', McpToolsetRegistry::class)
        ->args([service('mcp.store_api.capability_catalog')]);

    $services->set('mcp.store_api.list_request_handler', McpAllowlistListRequestHandler::class)
        ->args([
            service('mcp.server.store_api.registry'),
            null,
            param('shopware.mcp.pagination_limit'),
            param('shopware.store_api_mcp.advertised_tools'),
            service('mcp.store_api.toolset_registry'),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('mcp.store_api.request_handler');

    $services->set(StoreApiMcpServerController::class)
        ->public()
        ->args([
            service('mcp.server.store_api')->nullOnInvalid(),
            service(McpHttpTransportFactory::class),
            service(McpRateLimiter::class),
            service(McpSessionIdValidator::class),
            service('logger'),
            service('mcp.store_api.session_registry'),
            service('mcp.store_api.list_changed_notifier'),
        ])
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpPrivilegeProvider::class)
        ->args([service(Connection::class), service('logger')])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(McpCapabilityCatalog::class)
        ->args([
            service('mcp.server.admin.registry')->nullOnInvalid(),
            service(AppMcpPrivilegeProvider::class),
            param('shopware.mcp.tool_dependencies'),
            param('shopware.mcp.tool_privileges'),
            param('shopware.mcp.tool_groups'),
        ]);

    $services->set(McpToolsetRegistry::class)
        ->args([
            service(McpCapabilityCatalog::class),
            service(McpAllowlistProvider::class),
        ]);

    $services->set(McpToolListController::class)
        ->public()
        ->args([
            service('mcp.server.admin.builder')->nullOnInvalid(),
            service(McpCapabilityCatalog::class)->nullOnInvalid(),
        ])
        ->tag('controller.service_arguments');

    $services->set(IntegrationMcpAllowlistController::class)
        ->public()
        ->args([service('integration.repository')])
        ->tag('controller.service_arguments');

    $services->set(UserMcpAllowlistController::class)
        ->public()
        ->args([service('user.repository')])
        ->tag('controller.service_arguments');

    $services->set(DebugMcpCommand::class)
        ->args([
            service('mcp.server.admin.builder')->nullOnInvalid(),
            service('mcp.server.admin.registry')->nullOnInvalid(),
            service(McpAllowlistProvider::class),
            service(McpCapabilityCatalog::class),
            service('mcp.server.store_api.builder')->nullOnInvalid(),
            service('mcp.server.store_api.registry')->nullOnInvalid(),
            service('mcp.store_api.capability_catalog')->nullOnInvalid(),
            param('mcp.servers.unassigned'),
        ])
        ->tag('console.command');

    $services->set(ToolResultCacheStorage::class)
        ->args([service(Connection::class), service(ClockInterface::class)]);

    $services->set(ToolSearch::class);

    $services->set(McpToolsetSessionStorage::class)
        ->args([service(Connection::class), service(ClockInterface::class)]);

    $services->set(McpToolsetSessionCleanupTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(McpToolsetSessionCleanupTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(McpToolsetSessionStorage::class),
            service('mcp.server.admin.session.store')->nullOnInvalid(),
            service('mcp.server.store_api.session.store')->nullOnInvalid(),
        ])
        ->tag('messenger.message_handler');

    $services->set(McpSessionCleanupSubscriber::class)
        ->args([
            service(ToolResultCacheStorage::class),
            service(McpToolsetSessionStorage::class),
            service(McpSessionRegistry::class),
            service('mcp.store_api.session_registry'),
        ])
        ->tag('kernel.event_subscriber');

    $services->instanceof(McpToolResponse::class)
        ->call('setToolResultCache', [service(ToolResultCacheStorage::class), service('request_stack'), service('logger')])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    // Tools
    $services->set(ToolSearchTool::class)
        ->args([
            service('mcp.server.admin.registry')->nullOnInvalid(),
            service(ToolSearch::class),
            service(McpAllowlistProvider::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntitySchemaTool::class)
        ->args([service(DefinitionInstanceRegistry::class)])
        ->tag('mcp.tool');

    $services->set(EntitySearchTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(JsonEntityEncoder::class),
            service(AclCriteriaValidator::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityAggregateTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(AclCriteriaValidator::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityReadTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(JsonEntityEncoder::class),
            service(AclCriteriaValidator::class),
        ])
        ->tag('mcp.tool');

    $services->set(SystemConfigReadTool::class)
        ->args([
            service(SystemConfigService::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityUpsertTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
            service(Connection::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityDeleteTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
            service(Connection::class),
        ])
        ->tag('mcp.tool');

    $services->set(SystemConfigWriteTool::class)
        ->args([
            service(SystemConfigService::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool');

    $services->set(OrderStateTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
            service(StateMachineRegistry::class),
            service(Connection::class),
        ])
        ->tag('mcp.tool');

    $services->set(MediaUploadTool::class)
        ->args([
            service(MediaUploadService::class),
            service(McpContextProvider::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('mcp.tool');

    $services->set(StoreApiContextTool::class)
        ->args([service(StoreApiMcpContextProvider::class)])
        ->tag('shopware.store_api_mcp.tool');

    $services->set(StoreApiToolSearchTool::class)
        ->args([
            service('mcp.server.store_api.registry')->nullOnInvalid(),
            service(ToolSearch::class),
            null,
        ])
        ->tag('shopware.store_api_mcp.tool');

    $services->set(StoreApiToolsetsListTool::class)
        ->args([
            service('mcp.store_api.toolset_registry'),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('shopware.store_api_mcp.tool');

    $services->set(StoreApiToolsetEnableTool::class)
        ->args([
            service('mcp.store_api.toolset_registry'),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('shopware.store_api_mcp.tool');

    $services->set(ToolsetsListTool::class)
        ->args([
            service(McpToolsetRegistry::class),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('mcp.tool');

    $services->set(ToolsetEnableTool::class)
        ->args([
            service(McpToolsetRegistry::class),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('mcp.tool');

    // Prompt
    $services->set(ShopwareContextPrompt::class)
        ->tag('mcp.prompt');

    // Resources
    $services->set(EntityListResource::class)
        ->args([service(DefinitionInstanceRegistry::class)])
        ->tag('mcp.resource');

    $services->set(BusinessEventsResource::class)
        ->args([
            service(BusinessEventCollector::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.resource');

    $services->set(FlowActionsResource::class)
        ->args([
            service(FlowActionCollector::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.resource');

    $services->set(SalesChannelListResource::class)
        ->args([service('sales_channel.repository')])
        ->tag('mcp.resource');

    $services->set(CurrencyListResource::class)
        ->args([service('currency.repository')])
        ->tag('mcp.resource');

    $services->set(LanguageListResource::class)
        ->args([service('language.repository')])
        ->tag('mcp.resource');

    $services->set(StateMachineResource::class)
        ->args([service('state_machine.repository')])
        ->tag('mcp.resource');

    $services->set(ExtensionsResource::class)
        ->args([
            service(Connection::class),
            service('kernel'),
        ])
        ->tag('mcp.resource');

    $services->set(ToolResultResource::class)
        ->args([service(ToolResultCacheStorage::class)])
        ->tag('mcp.resource_template');

    // App MCP Tool pipeline
    $services->set(AppMcpCapabilityExecutor::class)
        ->args([
            service('shopware.app_system.guzzle'),
            env('APP_URL'),
            service(ShopIdProvider::class),
            param('shopware.mcp.app_tool_timeout'),
            service('logger'),
            service('kernel'),
            service('request_stack'),
            service('router'),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpToolLoader::class)
        ->args([
            service(Connection::class),
            service(AppMcpCapabilityExecutor::class),
            service('logger'),
            param('shopware.mcp.allowed_tools'),
        ])
        ->tag('mcp.loader');

    $services->set(AppMcpPromptLoader::class)
        ->args([
            service(Connection::class),
            service(AppMcpCapabilityExecutor::class),
            service('logger'),
        ])
        ->tag('mcp.loader')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpResourceLoader::class)
        ->args([
            service(Connection::class),
            service(AppMcpCapabilityExecutor::class),
            service('logger'),
        ])
        ->tag('mcp.loader')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(McpToolPersister::class)
        ->args([service('app_mcp_tool.repository')]);

    $services->set(McpPromptPersister::class)
        ->args([service('app_mcp_prompt.repository')]);

    $services->set(McpResourcePersister::class)
        ->args([service('app_mcp_resource.repository')]);

    $services->set(McpLifecycleHandler::class)
        ->args([
            service(McpToolPersister::class),
            service(McpPromptPersister::class),
            service(McpResourcePersister::class),
            service(AppMcpCapabilityDetector::class),
            service(McpListChangedNotifier::class),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -1300]);

    // DAL definitions
    $services->set(AppMcpToolDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppMcpToolTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppMcpPromptDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppMcpPromptTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppMcpResourceDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppMcpResourceTranslationDefinition::class)
        ->tag('shopware.entity.definition');
};
