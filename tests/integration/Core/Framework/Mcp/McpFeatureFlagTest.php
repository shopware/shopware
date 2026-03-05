<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Command\DebugMcpCommand;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolExecutor;
use Shopware\Core\Framework\Mcp\Loader\AppMcpToolLoader;
use Shopware\Core\Framework\Mcp\Prompt\ShopwareContextPrompt;
use Shopware\Core\Framework\Mcp\Resource\EntityListResource;
use Shopware\Core\Framework\Mcp\Tool\ApiRoutesTool;
use Shopware\Core\Framework\Mcp\Tool\BusinessEventsTool;
use Shopware\Core\Framework\Mcp\Tool\ConsoleCommandTool;
use Shopware\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Shopware\Core\Framework\Mcp\Tool\EntityReadTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopware\Core\Framework\Mcp\Tool\EntitySearchTool;
use Shopware\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Shopware\Core\Framework\Mcp\Tool\FlowActionsTool;
use Shopware\Core\Framework\Mcp\Tool\StateMachineTransitionTool;
use Shopware\Core\Framework\Mcp\Tool\StorefrontSearchTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Shopware\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 *
 * Verifies that all MCP services are registered when MCP_SERVER feature flag is active.
 * Skipped when the flag is inactive (default). Set MCP_SERVER=1 env var to run.
 */
#[Package('framework')]
class McpFeatureFlagTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @return list<array{string}>
     */
    public static function mcpServiceProvider(): array
    {
        return [
            [McpContextProvider::class],
            [McpServerController::class],
            [DebugMcpCommand::class],
            [EntitySchemaTool::class],
            [EntitySearchTool::class],
            [EntityReadTool::class],
            [EntityUpsertTool::class],
            [EntityDeleteTool::class],
            [SystemConfigReadTool::class],
            [SystemConfigWriteTool::class],
            [StateMachineTransitionTool::class],
            [ApiRoutesTool::class],
            [BusinessEventsTool::class],
            [FlowActionsTool::class],
            [ConsoleCommandTool::class],
            [StorefrontSearchTool::class],
            [ShopwareContextPrompt::class],
            [EntityListResource::class],
            [AppMcpToolExecutor::class],
            [AppMcpToolLoader::class],
        ];
    }

    #[DataProvider('mcpServiceProvider')]
    public function testMcpServiceIsRegistered(string $serviceClass): void
    {
        Feature::skipTestIfInActive('MCP_SERVER', $this);

        static::assertTrue(
            static::getContainer()->has($serviceClass),
            \sprintf('Service "%s" should be registered when MCP_SERVER flag is active.', $serviceClass),
        );
    }
}
