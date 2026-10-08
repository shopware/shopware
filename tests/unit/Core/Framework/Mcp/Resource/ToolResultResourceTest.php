<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Resource;

use Mcp\Exception\ResourceNotFoundException;
use Mcp\Schema\JsonRpc\Request as JsonRpcRequest;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Resource\ToolResultResource;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
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
        $storage->expects($this->once())->method('read')
            ->willReturnCallback(function (string $resultId, string $session) use ($id, $sessionId): array {
                static::assertSame($id, $resultId);
                static::assertSame($sessionId, $session);

                return ['content' => '{"success":true}', 'mimeType' => 'application/json'];
            });

        $resource = new ToolResultResource($storage, new RequestStack());
        $result = ($resource)($id, $this->makeContext($sessionId));

        static::assertSame('shopware://tool-result/' . $id, $result['uri']);
        static::assertSame('application/json', $result['mimeType']);
        static::assertSame('{"success":true}', $result['text']);
    }

    public function testInvokeThrowsForSessionMismatch(): void
    {
        $id = Uuid::randomHex();

        $storage = static::createStub(ToolResultCacheStorage::class);
        $storage->method('read')->willReturn(null);

        $resource = new ToolResultResource($storage, new RequestStack());

        $this->expectException(ResourceNotFoundException::class);

        ($resource)($id, $this->makeContext('00000000-0000-0000-0000-000000000002'));
    }

    public function testInvokeThrowsForUnknownId(): void
    {
        $id = Uuid::randomHex();

        $storage = static::createStub(ToolResultCacheStorage::class);
        $storage->method('read')->willReturn(null);

        $resource = new ToolResultResource($storage, new RequestStack());

        $this->expectException(ResourceNotFoundException::class);

        ($resource)($id, $this->makeContext('00000000-0000-0000-0000-000000000001'));
    }

    public function testASignedPointerIsReadForTheCallingPrincipal(): void
    {
        $token = Uuid::randomHex() . '.1790000000.signature';

        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->never())->method('read');
        $storage->expects($this->once())->method('readFor')
            ->with($token, 'admin:integration-id:')
            ->willReturn(['content' => '{"success":true}', 'mimeType' => 'application/json']);

        $result = (new ToolResultResource($storage, $this->requestStackFor('integration-id')))($token, $this->makeContext('00000000-0000-0000-0000-000000000001'));

        static::assertSame('shopware://tool-result/' . $token, $result['uri']);
        static::assertSame('{"success":true}', $result['text']);
    }

    public function testASignedPointerIsNotReadWithoutAnAuthenticatedPrincipal(): void
    {
        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->never())->method('readFor');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $this->expectException(ResourceNotFoundException::class);

        (new ToolResultResource($storage, $requestStack))(Uuid::randomHex() . '.1790000000.signature', $this->makeContext('00000000-0000-0000-0000-000000000001'));
    }

    public function testASignedPointerOfAnotherPrincipalIsNotFound(): void
    {
        $storage = static::createStub(ToolResultCacheStorage::class);
        $storage->method('readFor')->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);

        (new ToolResultResource($storage, $this->requestStackFor('other-integration')))(Uuid::randomHex() . '.1790000000.signature', $this->makeContext('00000000-0000-0000-0000-000000000001'));
    }

    private function requestStackFor(string $integrationId): RequestStack
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, new Context(new AdminApiSource(null, $integrationId)));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    private function makeContext(string $sessionId): RequestContext
    {
        $session = static::createStub(SessionInterface::class);
        $session->method('getId')->willReturn(SymfonyUuid::fromString($sessionId));

        $request = static::createStub(JsonRpcRequest::class);

        return new RequestContext($session, $request);
    }
}
