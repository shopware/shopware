<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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
    private string $userId;

    private UserEntity $user;

    /**
     * @var EntityRepository<UserCollection>&MockObject
     */
    private EntityRepository&MockObject $repository;

    private UserMcpAllowlistController $controller;

    private Context $context;

    protected function setUp(): void
    {
        $_SERVER['MCP_SERVER'] = '1';

        $this->userId = Uuid::randomHex();
        $this->user = new UserEntity();
        $this->user->setId($this->userId);

        $this->repository = $this->createMock(EntityRepository::class);
        $this->repository->method('search')->willReturn($this->makeSearchResult([$this->user]));

        $this->controller = new UserMcpAllowlistController($this->repository);
        $this->context = Context::createDefaultContext();
    }

    protected function tearDown(): void
    {
        unset($_SERVER['MCP_SERVER']);
    }

    public function testSaveReturnsNotFoundWhenFeatureFlagIsOff(): void
    {
        $_SERVER['MCP_SERVER'] = false;
        try {
            $repository = $this->createMock(EntityRepository::class);
            $repository->expects($this->never())->method('search');
            $repository->expects($this->never())->method('update');

            $controller = new UserMcpAllowlistController($repository);
            $response = $controller->save('some-id', $this->makeRequest(['allowlist' => null]), $this->context);

            static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        } finally {
            $_SERVER['MCP_SERVER'] = '1';
        }
    }

    public function testSaveStructuredAllowlist(): void
    {
        $allowlist = [
            'tools' => ['shopware-entity-read', 'shopware-entity-search'],
            'resources' => ['shopware://entities'],
            'prompts' => null,
        ];

        $savedContext = null;
        $entityEvent = $this->createMock(EntityWrittenContainerEvent::class);
        $this->repository->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $data, Context $context) use ($allowlist, $entityEvent, &$savedContext): EntityWrittenContainerEvent {
                static::assertSame([['id' => $this->userId, 'mcpAllowlist' => $allowlist]], $data);
                $savedContext = $context;

                return $entityEvent;
            });

        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => $allowlist]), $this->context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertNotNull($savedContext);
        static::assertSame(Context::SYSTEM_SCOPE, $savedContext->getScope());
    }

    public function testSaveAllowlistNull(): void
    {
        $this->repository->expects($this->once())
            ->method('update')
            ->with([['id' => $this->userId, 'mcpAllowlist' => null]]);

        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => null]), $this->context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testSaveAllowlistWithAllNullTypes(): void
    {
        $allowlist = ['tools' => null, 'resources' => null, 'prompts' => null];

        $this->repository->expects($this->once())
            ->method('update')
            ->with([['id' => $this->userId, 'mcpAllowlist' => $allowlist]]);

        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => $allowlist]), $this->context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testSaveAllowlistWithEmptyArraysDeniesAll(): void
    {
        $allowlist = ['tools' => [], 'resources' => [], 'prompts' => []];

        $this->repository->expects($this->once())
            ->method('update')
            ->with([['id' => $this->userId, 'mcpAllowlist' => $allowlist]]);

        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => $allowlist]), $this->context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAllowlistWithSubsetOfKnownKeysIsAccepted(): void
    {
        $allowlist = ['tools' => null];

        $this->repository->expects($this->once())
            ->method('update')
            ->with([['id' => $this->userId, 'mcpAllowlist' => $allowlist]]);

        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => $allowlist]), $this->context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testUserNotFound(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([]));
        $repository->expects($this->never())->method('update');

        $controller = new UserMcpAllowlistController($repository);

        $response = $controller->save(Uuid::randomHex(), $this->makeRequest(['allowlist' => null]), $this->context);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testMissingAllowlistKeyReturnsBadRequest(): void
    {
        $this->repository->expects($this->never())->method('update');

        $response = $this->controller->save($this->userId, $this->makeRequest([]), $this->context);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistTypeReturnsBadRequest(): void
    {
        $this->repository->expects($this->never())->method('update');

        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => 'not-an-array']), $this->context);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistWithNonStringToolsReturnsBadRequest(): void
    {
        $this->repository->expects($this->never())->method('update');

        $response = $this->controller->save(
            $this->userId,
            $this->makeRequest(['allowlist' => ['tools' => [1, 2, 3], 'resources' => null, 'prompts' => null]]),
            $this->context,
        );

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistWithNonStringResourcesReturnsBadRequest(): void
    {
        $this->repository->expects($this->never())->method('update');

        $response = $this->controller->save(
            $this->userId,
            $this->makeRequest(['allowlist' => ['tools' => null, 'resources' => [true, false], 'prompts' => null]]),
            $this->context,
        );

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testAllowlistWithUnknownKeyReturnsBadRequest(): void
    {
        $this->repository->expects($this->never())->method('update');

        $response = $this->controller->save(
            $this->userId,
            $this->makeRequest(['allowlist' => ['tools' => null, 'unknownKey' => 'value']]),
            $this->context,
        );

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistWithNonArrayTypeValueReturnsBadRequest(): void
    {
        $this->repository->expects($this->never())->method('update');

        $response = $this->controller->save(
            $this->userId,
            $this->makeRequest(['allowlist' => ['tools' => null, 'resources' => null, 'prompts' => 'not-valid']]),
            $this->context,
        );

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
