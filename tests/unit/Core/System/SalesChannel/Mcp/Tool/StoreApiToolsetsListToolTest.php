<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Mcp\Tool;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server\Stateless\RequestMeta;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpRequestedToolsetResolver;
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

        $tool = new StoreApiToolsetsListTool($registry, $storage, $requestStack, new McpRequestedToolsetResolver($requestStack));
        $result = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame('store-api', $result['data']['toolsets'][0]['name']);
        static::assertTrue($result['data']['toolsets'][0]['enabled']);
        static::assertSame('tool-groups', $result['_meta']['taxonomy']);
    }

    public function testIsUnavailableOnTheStatelessEra(): void
    {
        $registry = $this->createMock(McpToolsetRegistry::class);
        $registry->expects($this->never())->method('toolsets');

        $requestStack = new RequestStack();
        $requestStack->push(self::statelessRequest());

        $tool = new StoreApiToolsetsListTool($registry, static::createStub(McpToolsetSessionStorage::class), $requestStack, new McpRequestedToolsetResolver($requestStack));
        $result = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertStringContainsString('?toolsets=', $result['error']);
    }

    private static function statelessRequest(): Request
    {
        return Request::create('/store-api/_mcp', 'POST', content: json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['_meta' => [RequestMeta::PROTOCOL_VERSION => ProtocolVersion::V2026_07_28->value]],
        ], \JSON_THROW_ON_ERROR));
    }
}
