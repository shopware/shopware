<?php declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Content\Media\Upload\MediaUploadService;
use Shopware\Core\Framework\Api\OAuth\ClientRepository;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\Aggregate\AppMcpPrompt\AppMcpPromptDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpPromptTranslation\AppMcpPromptTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpResource\AppMcpResourceDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpResourceTranslation\AppMcpResourceTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpToolTranslation\AppMcpToolTranslationDefinition;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpPromptPersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpResourcePersister;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpToolPersister;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Authentication\McpAuthenticationListener;
use Shopware\Core\Framework\Mcp\Authentication\McpExceptionListener;
use Shopware\Core\Framework\Mcp\Command\DebugMcpCommand;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Controller\IntegrationMcpAllowlistController;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Controller\McpToolListController;
use Shopware\Core\Framework\Mcp\Controller\UserMcpAllowlistController;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPromptLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpResourceLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolLoader;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;
use Shopware\Core\Framework\Mcp\Prompt\ShopwareContextPrompt;
use Shopware\Core\Framework\Mcp\Resource\BusinessEventsResource;
use Shopware\Core\Framework\Mcp\Resource\CurrencyListResource;
use Shopware\Core\Framework\Mcp\Resource\EntityListResource;
use Shopware\Core\Framework\Mcp\Resource\ExtensionsResource;
use Shopware\Core\Framework\Mcp\Resource\FlowActionsResource;
use Shopware\Core\Framework\Mcp\Resource\LanguageListResource;
use Shopware\Core\Framework\Mcp\Resource\SalesChannelListResource;
use Shopware\Core\Framework\Mcp\Resource\StateMachineResource;
use Shopware\Core\Framework\Mcp\Resource\ToolResultResource;
use Shopware\Core\Framework\Mcp\Session\McpSessionCleanupSubscriber;
use Shopware\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\Tool\MediaUploadTool;
use Shopware\Core\Framework\Mcp\Tool\OrderStateTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('shopware.mcp.discovery_cache', Psr16Cache::class)
        ->args([service('cache.system')]);

    $services->set(McpContextProvider::class)
        ->args([service('request_stack')]);

    $services->set(McpAllowlistFilter::class);

    $services->set(McpAllowlistProvider::class)
        ->args([
            service(Connection::class),
            service('request_stack'),
            param('shopware.mcp.tool_dependencies'),
        ]);

    $services->set(McpAuthenticationListener::class)
        ->args([
            service(ClientRepository::class),
            service(RateLimiter::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(McpExceptionListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(McpServerController::class)
        ->public()
        ->args([
            service('mcp.server')->nullOnInvalid(),
            service('mcp.psr_http_factory')->nullOnInvalid(),
            service('mcp.http_foundation_factory')->nullOnInvalid(),
            service('mcp.psr17_factory')->nullOnInvalid(),
            service('mcp.psr17_factory')->nullOnInvalid(),
            service(RateLimiter::class),
            service(McpAllowlistProvider::class),
            service('logger'),
            service(McpAllowlistFilter::class),
        ])
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpPrivilegeProvider::class)
        ->args([service(Connection::class), service('logger')])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(McpCapabilityCatalog::class)
        ->args([
            service('mcp.registry')->nullOnInvalid(),
            service(AppMcpPrivilegeProvider::class),
            param('shopware.mcp.tool_dependencies'),
            param('shopware.mcp.tool_privileges'),
        ]);

    $services->set(McpToolListController::class)
        ->public()
        ->args([
            service('mcp.server.builder')->nullOnInvalid(),
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
            service('mcp.server.builder')->nullOnInvalid(),
            service('mcp.registry')->nullOnInvalid(),
            service(McpAllowlistProvider::class),
            service(McpCapabilityCatalog::class),
        ])
        ->tag('console.command');

    $services->set(ToolResultCacheStorage::class)
        ->args([service(Connection::class), service(ClockInterface::class)]);

    $services->set(McpSessionCleanupSubscriber::class)
        ->args([service(ToolResultCacheStorage::class)])
        ->tag('kernel.event_subscriber');

    $services->instanceof(McpToolResponse::class)
        ->call('setToolResultCache', [service(ToolResultCacheStorage::class), service('request_stack'), service('logger')])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    // Tools
    $services->set(EntitySchemaTool::class)
        ->args([service(DefinitionInstanceRegistry::class)])
        ->tag('mcp.tool');

    $services->set(EntitySearchTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(JsonEntityEncoder::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityAggregateTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityReadTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(JsonEntityEncoder::class),
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

    $services->set(McpPersister::class)
        ->args([
            service(McpToolPersister::class),
            service(McpPromptPersister::class),
            service(McpResourcePersister::class),
        ])
        ->tag('shopware.app_lifecycle.persister', ['priority' => -1300])
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

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
