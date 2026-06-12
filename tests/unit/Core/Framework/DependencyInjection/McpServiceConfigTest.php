<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Handler\McpLifecycleHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
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
use Shopware\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopware\Core\Framework\Mcp\Tool\MediaUploadTool;
use Shopware\Core\Framework\Mcp\Tool\OrderStateTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * @internal
 */
#[CoversNothing]
#[Package('framework')]
class McpServiceConfigTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $loader = new PhpFileLoader($this->container, new FileLocator());
        $loader->load(__DIR__ . '/../../../../../src/Core/Framework/DependencyInjection/mcp.php');
    }

    #[DataProvider('expectedServiceProvider')]
    public function testServiceIsRegistered(string $serviceId): void
    {
        static::assertTrue(
            $this->container->hasDefinition($serviceId),
            \sprintf('Service "%s" is not registered', $serviceId),
        );
    }

    #[DataProvider('toolServiceProvider')]
    public function testToolServiceIsTagged(string $serviceId): void
    {
        static::assertTrue(
            $this->container->getDefinition($serviceId)->hasTag('mcp.tool'),
            \sprintf('Service "%s" is not tagged with mcp.tool', $serviceId),
        );
    }

    #[DataProvider('resourceServiceProvider')]
    public function testResourceServiceIsTagged(string $serviceId): void
    {
        static::assertTrue(
            $this->container->getDefinition($serviceId)->hasTag('mcp.resource'),
            \sprintf('Service "%s" is not tagged with mcp.resource', $serviceId),
        );
    }

    public function testPromptServiceIsTagged(): void
    {
        static::assertTrue($this->container->getDefinition(ShopwareContextPrompt::class)->hasTag('mcp.prompt'));
    }

    public function testAppMcpToolLoaderIsTaggedAsMcpLoader(): void
    {
        static::assertTrue($this->container->getDefinition(AppMcpToolLoader::class)->hasTag('mcp.loader'));
    }

    public function testControllerIsPublic(): void
    {
        static::assertTrue($this->container->getDefinition(McpServerController::class)->isPublic());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function expectedServiceProvider(): iterable
    {
        yield McpLifecycleHandler::class => [McpLifecycleHandler::class];
        yield McpServerController::class => [McpServerController::class];
        yield EntitySchemaTool::class => [EntitySchemaTool::class];
        yield EntitySearchTool::class => [EntitySearchTool::class];
        yield EntityAggregateTool::class => [EntityAggregateTool::class];
        yield EntityReadTool::class => [EntityReadTool::class];
        yield EntityUpsertTool::class => [EntityUpsertTool::class];
        yield EntityDeleteTool::class => [EntityDeleteTool::class];
        yield SystemConfigReadTool::class => [SystemConfigReadTool::class];
        yield SystemConfigWriteTool::class => [SystemConfigWriteTool::class];
        yield OrderStateTool::class => [OrderStateTool::class];
        yield MediaUploadTool::class => [MediaUploadTool::class];
        yield ShopwareContextPrompt::class => [ShopwareContextPrompt::class];
        yield EntityListResource::class => [EntityListResource::class];
        yield BusinessEventsResource::class => [BusinessEventsResource::class];
        yield FlowActionsResource::class => [FlowActionsResource::class];
        yield SalesChannelListResource::class => [SalesChannelListResource::class];
        yield CurrencyListResource::class => [CurrencyListResource::class];
        yield LanguageListResource::class => [LanguageListResource::class];
        yield StateMachineResource::class => [StateMachineResource::class];
        yield ExtensionsResource::class => [ExtensionsResource::class];
        yield AppMcpCapabilityExecutor::class => [AppMcpCapabilityExecutor::class];
        yield AppMcpToolLoader::class => [AppMcpToolLoader::class];
        yield McpCapabilityCatalog::class => [McpCapabilityCatalog::class];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function toolServiceProvider(): iterable
    {
        yield EntitySchemaTool::class => [EntitySchemaTool::class];
        yield EntitySearchTool::class => [EntitySearchTool::class];
        yield EntityAggregateTool::class => [EntityAggregateTool::class];
        yield EntityReadTool::class => [EntityReadTool::class];
        yield EntityUpsertTool::class => [EntityUpsertTool::class];
        yield EntityDeleteTool::class => [EntityDeleteTool::class];
        yield SystemConfigReadTool::class => [SystemConfigReadTool::class];
        yield SystemConfigWriteTool::class => [SystemConfigWriteTool::class];
        yield OrderStateTool::class => [OrderStateTool::class];
        yield MediaUploadTool::class => [MediaUploadTool::class];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function resourceServiceProvider(): iterable
    {
        yield EntityListResource::class => [EntityListResource::class];
        yield BusinessEventsResource::class => [BusinessEventsResource::class];
        yield FlowActionsResource::class => [FlowActionsResource::class];
        yield SalesChannelListResource::class => [SalesChannelListResource::class];
        yield CurrencyListResource::class => [CurrencyListResource::class];
        yield LanguageListResource::class => [LanguageListResource::class];
        yield StateMachineResource::class => [StateMachineResource::class];
        yield ExtensionsResource::class => [ExtensionsResource::class];
    }
}
