<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Mcp\Controller\UserMcpAllowlistController;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(UserMcpAllowlistController::class)]
class UserMcpAllowlistControllerTest extends TestCase
{
    public function testSaveStructuredAllowlist(): void
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        $allowlist = [
            'tools' => ['shopware-entity-read', 'shopware-entity-search'],
            'resources' => ['shopware://entities'],
            'prompts' => null,
        ];

        $savedContext = null;
        $entityEvent = $this->createMock(EntityWrittenContainerEvent::class);
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$user]));
        $repository->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $data, Context $context) use ($userId, $allowlist, $entityEvent, &$savedContext): EntityWrittenContainerEvent {
                static::assertSame([['id' => $userId, 'mcpAllowlist' => $allowlist]], $data);
                $savedContext = $context;

                return $entityEvent;
            });

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($userId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNotNull($savedContext);
        static::assertSame(Context::SYSTEM_SCOPE, $savedContext->getScope());
    }

    public function testSaveAllowlistNull(): void
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$user]));
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $userId, 'mcpAllowlist' => null]]);

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => null]);

        $response = $controller->save($userId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testSaveAllowlistWithAllNullTypes(): void
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        $allowlist = ['tools' => null, 'resources' => null, 'prompts' => null];

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$user]));
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $userId, 'mcpAllowlist' => $allowlist]]);

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($userId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testSaveAllowlistWithEmptyArraysDeniesAll(): void
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        $allowlist = ['tools' => [], 'resources' => [], 'prompts' => []];

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$user]));
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $userId, 'mcpAllowlist' => $allowlist]]);

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($userId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAllowlistWithSubsetOfKnownKeysIsAccepted(): void
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        $allowlist = ['tools' => null];

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$user]));
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $userId, 'mcpAllowlist' => $allowlist]]);

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($userId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testUserNotFound(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([]));
        $repository->expects($this->never())->method('update');

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => null]);

        $response = $controller->save(Uuid::randomHex(), $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testMissingAllowlistKeyReturnsBadRequest(): void
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$user]));
        $repository->expects($this->never())->method('update');

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest([]);

        $response = $controller->save($userId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistTypeReturnsBadRequest(): void
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$user]));
        $repository->expects($this->never())->method('update');

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => 'not-an-array']);

        $response = $controller->save($userId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistWithNonStringToolsReturnsBadRequest(): void
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$user]));
        $repository->expects($this->never())->method('update');

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => ['tools' => [1, 2, 3], 'resources' => null, 'prompts' => null]]);

        $response = $controller->save($userId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistWithNonStringResourcesReturnsBadRequest(): void
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$user]));
        $repository->expects($this->never())->method('update');

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => ['tools' => null, 'resources' => [true, false], 'prompts' => null]]);

        $response = $controller->save($userId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testAllowlistWithUnknownKeyReturnsBadRequest(): void
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$user]));
        $repository->expects($this->never())->method('update');

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => ['tools' => null, 'unknownKey' => 'value']]);

        $response = $controller->save($userId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistWithNonArrayTypeValueReturnsBadRequest(): void
    {
        $userId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId($userId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$user]));
        $repository->expects($this->never())->method('update');

        $controller = new UserMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => ['tools' => null, 'resources' => null, 'prompts' => 'not-valid']]);

        $response = $controller->save($userId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @param array<string, mixed> $body
     */
    private function makeRequest(array $body): Request
    {
        $request = Request::create('', 'POST', [], [], [], [], json_encode($body, \JSON_THROW_ON_ERROR));
        $request->headers->set('Content-Type', 'application/json');

        return $request;
    }

    /**
     * @param list<UserEntity> $entities
     *
     * @return EntitySearchResult<UserCollection>
     */
    private function makeSearchResult(array $entities): EntitySearchResult
    {
        $collection = new UserCollection($entities);

        return new EntitySearchResult(
            'user',
            \count($entities),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
