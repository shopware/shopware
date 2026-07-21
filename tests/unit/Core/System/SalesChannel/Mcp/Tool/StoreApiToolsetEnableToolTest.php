<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Shopware\Core\System\SalesChannel\Mcp\Tool\StoreApiToolsetEnableTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StoreApiToolsetEnableTool::class)]
class StoreApiToolsetEnableToolTest extends TestCase
{
    public function testEnablesToolsetThroughStoreApiSubclass(): void
    {
        $registry = $this->createMock(McpToolsetRegistry::class);
        $registry->expects($this->once())
            ->method('find')
            ->with('store-api')
            ->willReturn([
                'name' => 'store-api',
                'title' => 'Store API tools',
                'description' => 'Store API',
                'tools' => ['shopware-store-api-context'],
            ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())
            ->method('enable')
            ->with('session-a', 'store-api');

        $requestStack = new RequestStack();
        $request = Request::create('/store-api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new StoreApiToolsetEnableTool($registry, $storage, $requestStack);
        $result = json_decode($tool('store-api'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame('store-api', $result['data']['toolset']['name']);
        static::assertTrue($result['_meta']['listChanged']);
        // Like the admin tool, the store-api variant records intent; the controller emits it.
        static::assertTrue($request->attributes->getBoolean(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE));
    }

    public function testInvokeIsDeclaredOnConcreteClassSoDiscoveryBindsToIt(): void
    {
        // The MCP SDK discoverer binds a tool handler to __invoke's declaring class, and the store-api
        // service locator keys on the service id (= class). If __invoke were only inherited from
        // ToolsetEnableTool, discovery would bind the handler to the admin base and the store-api
        // tool would resolve to the wrong (admin-wired) instance.
        $method = new \ReflectionMethod(StoreApiToolsetEnableTool::class, '__invoke');

        static::assertSame(StoreApiToolsetEnableTool::class, $method->getDeclaringClass()->getName());
    }
}
