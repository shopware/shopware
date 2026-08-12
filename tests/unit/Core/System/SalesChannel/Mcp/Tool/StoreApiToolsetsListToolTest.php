<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolsetsListTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiToolsetsListTool::class)]
class StoreApiToolsetsListToolTest extends TestCase
{
    public function testListsToolsetsThroughStoreApiSubclass(): void
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('toolsets')->willReturn([
            [
                'name' => 'store-api',
                'title' => 'Store API tools',
                'description' => 'Store API',
                'tools' => ['shopware-store-api-context'],
            ],
        ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())
            ->method('enabledToolsets')
            ->with('session-a')
            ->willReturn(['store-api']);

        $requestStack = new RequestStack();
        $request = Request::create('/store-api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new StoreApiToolsetsListTool($registry, $storage, $requestStack);
        $result = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame('store-api', $result['data']['toolsets'][0]['name']);
        static::assertTrue($result['data']['toolsets'][0]['enabled']);
        static::assertSame('tool-groups', $result['_meta']['taxonomy']);
    }

    public function testInvokeIsDeclaredOnConcreteClassSoDiscoveryBindsToIt(): void
    {
        // The MCP SDK discoverer binds a tool handler to __invoke's declaring class, and the store-api
        // service locator keys on the service id (= class). If __invoke were only inherited from
        // ToolsetsListTool, discovery would bind the handler to the admin base and the store-api
        // tool would resolve to the wrong (admin-wired) instance.
        $method = new \ReflectionMethod(StoreApiToolsetsListTool::class, '__invoke');

        static::assertSame(StoreApiToolsetsListTool::class, $method->getDeclaringClass()->getName());
    }
}
