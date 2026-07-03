<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\McpToolsetSessionStorage;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\Tool\ToolsetsListTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(ToolsetsListTool::class)]
#[CoversClass(McpToolResponse::class)]
class ToolsetsListToolTest extends TestCase
{
    public function testListsToolsetsWithEnabledSessionState(): void
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('toolsets')->willReturn([
            [
                'name' => 'default',
                'title' => 'Default tools',
                'description' => 'Default',
                'tools' => ['shopware-toolsets-list'],
                'enabledByDefault' => true,
            ],
            [
                'name' => 'shopware-entity',
                'title' => 'Entity tools',
                'description' => 'Entity',
                'tools' => ['shopware-entity-search'],
                'enabledByDefault' => false,
            ],
        ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->once())
            ->method('enabledToolsets')
            ->with('session-a')
            ->willReturn(['shopware-entity']);

        $requestStack = new RequestStack();
        $request = Request::create('/api/_mcp', 'POST');
        $request->headers->set('Mcp-Session-Id', 'session-a');
        $requestStack->push($request);

        $tool = new ToolsetsListTool($registry, $storage, $requestStack);
        $result = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertTrue($result['data']['toolsets'][0]['enabled']);
        static::assertTrue($result['data']['toolsets'][1]['enabled']);
        static::assertSame('prefix-fallback', $result['_meta']['taxonomy']);
    }

    public function testListsToolsetsWithoutSessionState(): void
    {
        $registry = static::createStub(McpToolsetRegistry::class);
        $registry->method('toolsets')->willReturn([
            [
                'name' => 'default',
                'title' => 'Default tools',
                'description' => 'Default',
                'tools' => ['shopware-toolsets-list'],
                'enabledByDefault' => true,
            ],
            [
                'name' => 'shopware-entity',
                'title' => 'Entity tools',
                'description' => 'Entity',
                'tools' => ['shopware-entity-search'],
                'enabledByDefault' => false,
            ],
        ]);

        $storage = $this->createMock(McpToolsetSessionStorage::class);
        $storage->expects($this->never())->method('enabledToolsets');

        $tool = new ToolsetsListTool($registry, $storage, new RequestStack());
        $result = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertTrue($result['data']['toolsets'][0]['enabled']);
        static::assertFalse($result['data']['toolsets'][1]['enabled']);
    }
}
