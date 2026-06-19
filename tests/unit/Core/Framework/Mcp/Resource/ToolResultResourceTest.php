<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Resource;

use Mcp\Exception\ResourceNotFoundException;
use Mcp\Schema\JsonRpc\Request as JsonRpcRequest;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Resource\ToolResultResource;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Uid\Uuid as SymfonyUuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ToolResultResource::class)]
class ToolResultResourceTest extends TestCase
{
    public function testInvokeReturnsStoredResult(): void
    {
        $id = Uuid::randomHex();
        $sessionId = '00000000-0000-0000-0000-000000000001';

        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->method('read')
            ->with($id, $sessionId)
            ->willReturn(['content' => '{"success":true}', 'mimeType' => 'application/json']);

        $resource = new ToolResultResource($storage);
        $result = ($resource)($id, $this->makeContext($sessionId));

        static::assertSame('shopware://tool-result/' . $id, $result['uri']);
        static::assertSame('application/json', $result['mimeType']);
        static::assertSame('{"success":true}', $result['text']);
    }

    public function testInvokeThrowsForSessionMismatch(): void
    {
        $id = Uuid::randomHex();

        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->method('read')->willReturn(null);

        $resource = new ToolResultResource($storage);

        $this->expectException(ResourceNotFoundException::class);

        ($resource)($id, $this->makeContext('00000000-0000-0000-0000-000000000002'));
    }

    public function testInvokeThrowsForUnknownId(): void
    {
        $id = Uuid::randomHex();

        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->method('read')->willReturn(null);

        $resource = new ToolResultResource($storage);

        $this->expectException(ResourceNotFoundException::class);

        ($resource)($id, $this->makeContext('00000000-0000-0000-0000-000000000001'));
    }

    private function makeContext(string $sessionId): RequestContext
    {
        $session = $this->createMock(SessionInterface::class);
        $session->method('getId')->willReturn(SymfonyUuid::fromString($sessionId));

        $request = $this->createMock(JsonRpcRequest::class);

        return new RequestContext($session, $request);
    }
}
