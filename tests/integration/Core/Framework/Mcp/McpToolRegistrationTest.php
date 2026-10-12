<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Mcp;

use Mcp\Capability\RegistryInterface;
use Mcp\Server;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\Tool\ToolSearchTool;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolSearchTool;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolsetEnableTool;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolsetsListTool;

/**
 * Checks the tools as the MCP servers publish them: the input schema clients receive, and the class each
 * tool's handler is bound to.
 *
 * @internal
 */
#[Package('framework')]
class McpToolRegistrationTest extends TestCase
{
    use KernelTestBehaviour;

    private const ADMIN = 'admin';

    private const STORE_API = 'store_api';

    /**
     * Clients only see the input schema, so every parameter needs a description for a model to call the tool
     * correctly.
     */
    #[DataProvider('describedTools')]
    public function testEveryParameterIsDescribedInThePublishedInputSchema(string $toolName): void
    {
        $properties = $this->registry(self::ADMIN)->getTool($toolName)->tool->inputSchema['properties'] ?? [];
        static::assertIsArray($properties);
        static::assertNotSame([], $properties);

        foreach ($properties as $name => $property) {
            static::assertIsArray($property);
            static::assertIsString($property['description'] ?? null, \sprintf('$%s has no description', $name));
            static::assertNotSame('', $property['description'], \sprintf('$%s has an empty description', $name));
        }
    }

    public static function describedTools(): \Generator
    {
        yield 'shopware-entity-aggregate' => ['shopware-entity-aggregate'];
        yield 'shopware-entity-read' => ['shopware-entity-read'];
        yield 'shopware-entity-search' => ['shopware-entity-search'];
        yield 'shopware-entity-upsert' => ['shopware-entity-upsert'];
    }

    /**
     * The SDK binds a handler to the class declaring `__invoke`. A tool that only inherited it would be bound to
     * its abstract or admin-side base class, and the store-api server would resolve the wrong instance.
     */
    #[DataProvider('handlerBindings')]
    public function testToolHandlerIsBoundToItsConcreteClass(string $server, string $toolName, string $expectedClass): void
    {
        static::assertSame(
            [$expectedClass, '__invoke'],
            $this->registry($server)->getTool($toolName)->handler,
        );
    }

    public static function handlerBindings(): \Generator
    {
        yield 'admin tool search' => [self::ADMIN, ToolSearchTool::NAME, ToolSearchTool::class];
        yield 'store-api tool search' => [self::STORE_API, StoreApiToolSearchTool::NAME, StoreApiToolSearchTool::class];
        yield 'store-api toolset enable' => [self::STORE_API, McpToolsetRegistry::ENABLE_TOOLSET_TOOL, StoreApiToolsetEnableTool::class];
        yield 'store-api toolsets list' => [self::STORE_API, McpToolsetRegistry::LIST_TOOLSETS_TOOL, StoreApiToolsetsListTool::class];
    }

    /**
     * The registry is filled while the server is built, so the server is resolved first.
     */
    private function registry(string $server): RegistryInterface
    {
        static::assertInstanceOf(Server::class, static::getContainer()->get('mcp.server.' . $server));

        $registry = static::getContainer()->get('mcp.server.' . $server . '.registry');
        static::assertInstanceOf(RegistryInterface::class, $registry);

        return $registry;
    }
}
