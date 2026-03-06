<?php declare(strict_types=1);

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Framework\Api\OAuth\ClientRepository;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\App\Aggregate\AppMcpTool\AppMcpToolDefinition;
use Shopware\Core\Framework\App\Aggregate\AppMcpToolTranslation\AppMcpToolTranslationDefinition;
use Shopware\Core\Framework\App\Lifecycle\Persister\McpToolPersister;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Mcp\Authentication\McpAuthenticationListener;
use Shopware\Core\Framework\Mcp\Command\DebugMcpCommand;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolLoader;
use Shopware\Core\Framework\Mcp\Prompt\ShopwareContextPrompt;
use Shopware\Core\Framework\Mcp\Resource\BusinessEventsResource;
use Shopware\Core\Framework\Mcp\Resource\CurrencyListResource;
use Shopware\Core\Framework\Mcp\Resource\EntityListResource;
use Shopware\Core\Framework\Mcp\Resource\FlowActionsResource;
use Shopware\Core\Framework\Mcp\Resource\LanguageListResource;
use Shopware\Core\Framework\Mcp\Resource\SalesChannelListResource;
use Shopware\Core\Framework\Mcp\Resource\StateMachineResource;
use Shopware\Core\Framework\Mcp\Tool\CartCheckoutTool;
use Shopware\Core\Framework\Mcp\Tool\CartManageTool;
use Shopware\Core\Framework\Mcp\Tool\CheckoutMethodsTool;
use Shopware\Core\Framework\Mcp\Tool\ConsoleCommandTool;
use Shopware\Core\Framework\Mcp\Tool\CustomerLookupTool;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopware\Core\Framework\Mcp\Tool\OrderSummaryTool;
use Shopware\Core\Framework\Mcp\Tool\ProductCreateTool;
use Shopware\Core\Framework\Mcp\Tool\RevenueReportTool;
use Shopware\Core\Framework\Mcp\Tool\StateMachineTransitionTool;
use Shopware\Core\Framework\Mcp\Tool\StorefrontSearchTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(McpContextProvider::class)
        ->args([service('request_stack')])
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(McpAuthenticationListener::class)
        ->args([
            service(ClientRepository::class),
            service(RateLimiter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(McpServerController::class)
        ->public()
        ->args([
            service('mcp.server'),
            service('mcp.psr_http_factory'),
            service('mcp.http_foundation_factory'),
            service('mcp.psr17_factory'),
            service('mcp.psr17_factory'),
            service(RateLimiter::class),
            service('logger'),
        ])
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER'])
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(DebugMcpCommand::class)
        ->args([
            tagged_iterator('mcp.tool'),
            tagged_iterator('mcp.prompt'),
            tagged_iterator('mcp.resource'),
        ])
        ->tag('console.command')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    // Tools
    $services->set(EntitySchemaTool::class)
        ->args([service(DefinitionInstanceRegistry::class)])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(EntitySearchTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(JsonEntityEncoder::class),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(EntityReadTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(JsonEntityEncoder::class),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(SystemConfigReadTool::class)
        ->args([service(SystemConfigService::class)])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(EntityUpsertTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
            service('Doctrine\DBAL\Connection'),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(EntityDeleteTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
            service('Doctrine\DBAL\Connection'),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(SystemConfigWriteTool::class)
        ->args([service(SystemConfigService::class)])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(StateMachineTransitionTool::class)
        ->args([
            service(StateMachineRegistry::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(ConsoleCommandTool::class)
        ->args([
            service('kernel'),
            param('shopware.mcp.allowed_console_commands'),
            service('logger'),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER'])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(StorefrontSearchTool::class)
        ->args([
            service(SalesChannelContextService::class),
            service('sales_channel.product.repository'),
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(JsonEntityEncoder::class),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(OrderSummaryTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(CustomerLookupTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(ProductCreateTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
            service('Doctrine\DBAL\Connection'),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(RevenueReportTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(CartManageTool::class)
        ->args([
            service(SalesChannelContextService::class),
            service(CartService::class),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(CartCheckoutTool::class)
        ->args([
            service(SalesChannelContextService::class),
            service(CartService::class),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(CheckoutMethodsTool::class)
        ->args([
            service(SalesChannelContextService::class),
            service(PaymentMethodRoute::class),
            service(ShippingMethodRoute::class),
        ])
        ->tag('mcp.tool')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    // Prompt
    $services->set(ShopwareContextPrompt::class)
        ->tag('mcp.prompt')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    // Resources
    $services->set(EntityListResource::class)
        ->args([service(DefinitionInstanceRegistry::class)])
        ->tag('mcp.resource')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(BusinessEventsResource::class)
        ->args([
            service(BusinessEventCollector::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.resource')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(FlowActionsResource::class)
        ->args([
            service(FlowActionCollector::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.resource')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(SalesChannelListResource::class)
        ->args([service('sales_channel.repository')])
        ->tag('mcp.resource')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(CurrencyListResource::class)
        ->args([service('currency.repository')])
        ->tag('mcp.resource')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(LanguageListResource::class)
        ->args([service('language.repository')])
        ->tag('mcp.resource')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(StateMachineResource::class)
        ->args([service('state_machine.repository')])
        ->tag('mcp.resource')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    // App MCP Tool pipeline
    $services->set(AppMcpToolExecutor::class)
        ->args([
            service('shopware.app_system.guzzle'),
            env('APP_URL'),
            param('shopware.mcp.app_tool_timeout'),
            service('logger'),
        ])
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER'])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpToolLoader::class)
        ->args([
            service('Doctrine\DBAL\Connection'),
            service(AppMcpToolExecutor::class),
            param('shopware.mcp.allowed_tools'),
        ])
        ->tag('mcp.loader')
        ->tag('shopware.feature', ['flag' => 'MCP_SERVER']);

    $services->set(McpToolPersister::class)
        ->args([service('app_mcp_tool.repository')]);

    // DAL definitions
    $services->set(AppMcpToolDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AppMcpToolTranslationDefinition::class)
        ->tag('shopware.entity.definition');
};
