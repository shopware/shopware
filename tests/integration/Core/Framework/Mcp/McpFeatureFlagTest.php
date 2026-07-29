<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Command\DebugMcpCommand;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPromptLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpResourceLoader;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolLoader;
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
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 *
 * Verifies that all MCP services are registered in the DI container.
 */
#[Package('framework')]
class McpFeatureFlagTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @return iterable<string, array{string}>
     */
    public static function mcpServiceProvider(): iterable
    {
        yield McpContextProvider::class => [McpContextProvider::class];
        yield McpServerController::class => [McpServerController::class];
        yield DebugMcpCommand::class => [DebugMcpCommand::class];
        yield EntitySchemaTool::class => [EntitySchemaTool::class];
        yield EntitySearchTool::class => [EntitySearchTool::class];
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
        yield EntityAggregateTool::class => [EntityAggregateTool::class];
        yield AppMcpCapabilityExecutor::class => [AppMcpCapabilityExecutor::class];
        yield AppMcpToolLoader::class => [AppMcpToolLoader::class];
        yield AppMcpPromptLoader::class => [AppMcpPromptLoader::class];
        yield AppMcpResourceLoader::class => [AppMcpResourceLoader::class];
    }

    #[DataProvider('mcpServiceProvider')]
    public function testMcpServiceIsRegistered(string $serviceClass): void
    {
        static::assertTrue(
            static::getContainer()->has($serviceClass),
            \sprintf('Service "%s" should be registered when MCP_SERVER flag is active.', $serviceClass),
        );
    }
}
