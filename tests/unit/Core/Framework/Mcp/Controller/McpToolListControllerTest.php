<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use Mcp\Capability\RegistryInterface;
use Mcp\Schema\Page;
use Mcp\Schema\Prompt;
use Mcp\Schema\Resource;
use Mcp\Schema\Tool;
use Mcp\Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Controller\McpToolListController;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(McpToolListController::class)]
#[CoversClass(McpCapabilityCatalog::class)]
class McpToolListControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['MCP_SERVER'] = '1';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['MCP_SERVER']);
    }

    public function testListReturnsEmptyArrayWhenNoToolsRegistered(): void
    {
        $controller = $this->makeController(new Page([], null));
        $response = $controller->list();

        static::assertSame(200, $response->getStatusCode());
        static::assertSame([], json_decode((string) $response->getContent(), true));
    }

    public function testListReturnsToolNameAndDescription(): void
    {
        $page = new Page([self::makeTool('shopware-entity-search', 'Search entities')], null);
        $controller = $this->makeController($page);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertCount(1, $data);
        static::assertSame('shopware-entity-search', $data[0]['name']);
        static::assertSame('Search entities', $data[0]['description']);
    }

    public function testListSortsToolsAlphabetically(): void
    {
        $page = new Page([
            self::makeTool('shopware-entity-upsert'),
            self::makeTool('shopware-entity-search'),
            self::makeTool('shopware-entity-delete'),
        ], null);

        $controller = $this->makeController($page);
        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertSame(
            ['shopware-entity-delete', 'shopware-entity-search', 'shopware-entity-upsert'],
            array_column($data, 'name'),
        );
    }

    public function testListIncludesDependenciesFromConfig(): void
    {
        $page = new Page([self::makeTool('shopware-entity-delete')], null);
        $controller = $this->makeController($page, [
            'shopware-entity-delete' => ['shopware-entity-search', 'shopware-entity-schema'],
        ]);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertSame(
            ['shopware-entity-search', 'shopware-entity-schema'],
            $data[0]['dependencies'],
        );
    }

    public function testListDefaultsToEmptyDependenciesWhenToolNotConfigured(): void
    {
        $page = new Page([self::makeTool('shopware-entity-schema')], null);
        $controller = $this->makeController($page);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertSame([], $data[0]['dependencies']);
    }

    public function testListIncludesPrivilegesFromCompileTimeConfig(): void
    {
        $page = new Page([self::makeTool('shopware-entity-delete')], null);
        $privileges = ['static' => ['product:read'], 'entityParam' => null, 'operations' => ['update']];
        $controller = $this->makeController($page, [], ['shopware-entity-delete' => $privileges]);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertSame(['product:read'], $data[0]['requiredPrivileges']['static']);
        static::assertNull($data[0]['requiredPrivileges']['entityParam']);
        static::assertSame(['update'], $data[0]['requiredPrivileges']['operations']);
    }

    public function testListMergesAppToolPrivilegesFromDb(): void
    {
        $page = new Page([self::makeTool('MyApp-my-tool')], null);

        $provider = $this->createMock(AppMcpPrivilegeProvider::class);
        $provider->method('getAppToolPrivileges')->willReturn([
            'MyApp-my-tool' => ['product:read', 'order:read'],
        ]);

        $controller = $this->makeController($page, [], [], $provider);
        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertSame(['product:read', 'order:read'], $data[0]['requiredPrivileges']['static']);
        static::assertNull($data[0]['requiredPrivileges']['entityParam']);
        static::assertSame([], $data[0]['requiredPrivileges']['operations']);
    }

    public function testCapabilitiesReturnsAllCapabilityTypes(): void
    {
        $toolsPage = new Page([self::makeTool('shopware-entity-search', 'Search')], null);
        $resourcesPage = new Page([
            new Resource('shopware://entities', 'entities', 'All entities', null, null, null),
        ], null);
        $promptsPage = new Page([
            new Prompt('shopware-context', null, 'Context prompt', []),
        ], null);

        $controller = $this->makeController($toolsPage, [], [], null, $resourcesPage, $promptsPage);
        $response = $controller->capabilities();

        static::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);

        static::assertArrayHasKey('tools', $data);
        static::assertArrayHasKey('resources', $data);
        static::assertArrayHasKey('prompts', $data);
        static::assertSame('shopware-entity-search', $data['tools'][0]['name']);
        static::assertSame('shopware://entities', $data['resources'][0]['uri']);
        static::assertSame('shopware-context', $data['prompts'][0]['name']);
    }

    public function testListIncludesTitleWhenSet(): void
    {
        $page = new Page([
            new Tool(
                name: 'shopware-entity-search',
                title: 'Entity Search',
                inputSchema: ['type' => 'object', 'properties' => [], 'required' => null],
                description: 'Search entities',
                annotations: null,
            ),
        ], null);
        $controller = $this->makeController($page);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertSame('Entity Search', $data[0]['title']);
    }

    public function testListHandlesNullDescription(): void
    {
        $page = new Page([self::makeTool('shopware-entity-schema', null)], null);
        $controller = $this->makeController($page);

        $data = json_decode((string) $controller->list()->getContent(), true);

        static::assertNull($data[0]['description']);
    }

    public function testListReturnsNotFoundWhenFeatureFlagIsOff(): void
    {
        $_SERVER['MCP_SERVER'] = false;
        try {
            $controller = $this->makeController(new Page([], null));
            static::assertSame(Response::HTTP_NOT_FOUND, $controller->list()->getStatusCode());
        } finally {
            $_SERVER['MCP_SERVER'] = '1';
        }
    }

    public function testCapabilitiesReturnsNotFoundWhenFeatureFlagIsOff(): void
    {
        $_SERVER['MCP_SERVER'] = false;
        try {
            $controller = $this->makeController(new Page([], null));
            static::assertSame(Response::HTTP_NOT_FOUND, $controller->capabilities()->getStatusCode());
        } finally {
            $_SERVER['MCP_SERVER'] = '1';
        }
    }

    private static function makeTool(string $name, ?string $description = null): Tool
    {
        return new Tool(
            name: $name,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => null],
            description: $description,
            annotations: null,
        );
    }

    /**
     * @param array<string, list<string>> $toolDependencies
     * @param array<string, array{static: list<string>, entityParam: ?string, operations: list<string>}> $toolPrivileges
     */
    private function makeController(
        Page $page,
        array $toolDependencies = [],
        array $toolPrivileges = [],
        ?AppMcpPrivilegeProvider $privilegeProvider = null,
        ?Page $resourcesPage = null,
        ?Page $promptsPage = null,
    ): McpToolListController {
        $registry = static::createStub(RegistryInterface::class);
        $registry->method('getTools')->willReturn($page);
        $registry->method('getResources')->willReturn($resourcesPage ?? new Page([], null));
        $registry->method('getPrompts')->willReturn($promptsPage ?? new Page([], null));

        if ($privilegeProvider === null) {
            $privilegeProvider = static::createStub(AppMcpPrivilegeProvider::class);
            $privilegeProvider->method('getAppToolPrivileges')->willReturn([]);
        }

        $catalog = new McpCapabilityCatalog($registry, $privilegeProvider, $toolDependencies, $toolPrivileges);

        return new McpToolListController(Server::builder(), $catalog);
    }
}
