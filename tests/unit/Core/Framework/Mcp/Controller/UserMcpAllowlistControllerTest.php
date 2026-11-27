<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Controller\UserMcpAllowlistController;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\User\UserCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UserMcpAllowlistController::class)]
class UserMcpAllowlistControllerTest extends TestCase
{
    private string $userId;

    /**
     * @var StaticEntityRepository<UserCollection>
     */
    private StaticEntityRepository $repository;

    private UserMcpAllowlistController $controller;

    private Context $context;

    protected function setUp(): void
    {
        $this->userId = Uuid::randomHex();

        $this->repository = StaticEntityRepository::of(UserCollection::class, [[$this->userId]]);

        $this->controller = new UserMcpAllowlistController($this->repository);
        $this->context = Context::createDefaultContext();
    }

    public function testSaveStructuredAllowlist(): void
    {
        $allowlist = [
            'tools' => ['shopware-entity-read', 'shopware-entity-search'],
            'resources' => ['shopware://entities'],
            'prompts' => null,
        ];

        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => $allowlist]), $this->context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([[['id' => $this->userId, 'mcpAllowlist' => $allowlist]]], $this->repository->updates);
    }

    public function testSaveAllowlistNull(): void
    {
        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => null]), $this->context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([[['id' => $this->userId, 'mcpAllowlist' => null]]], $this->repository->updates);
    }

    public function testSaveAllowlistWithAllNullTypes(): void
    {
        $allowlist = ['tools' => null, 'resources' => null, 'prompts' => null];

        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => $allowlist]), $this->context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([[['id' => $this->userId, 'mcpAllowlist' => $allowlist]]], $this->repository->updates);
    }

    public function testSaveAllowlistWithEmptyArraysDeniesAll(): void
    {
        $allowlist = ['tools' => [], 'resources' => [], 'prompts' => []];

        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => $allowlist]), $this->context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([[['id' => $this->userId, 'mcpAllowlist' => $allowlist]]], $this->repository->updates);
    }

    public function testRouteRequiresBothTheActionAndTheEntityPrivilege(): void
    {
        // The allowlist decides what a principal may reach over MCP, so writing an arbitrary
        // {userId} is a permission change and needs the entity privilege, not just the action one.
        $route = (new AttributeRouteControllerLoader())->load(UserMcpAllowlistController::class)->get('api.action.user.mcp-allowlist');

        static::assertNotNull($route);
        static::assertSame(['api_action_user_mcp-allowlist', 'user:update'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    public function testObjectShapedPerTypeValueIsRejected(): void
    {
        // A JSON object here would be stored but read back as an empty selection, so reject it
        // instead of silently persisting something that grants nothing.
        $request = $this->makeRequest(['allowlist' => ['tools' => ['x' => 'shopware-entity-delete']]]);

        $response = $this->controller->save($this->userId, $request, $this->context);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $this->repository->updates);
    }

    public function testAllowlistWithSubsetOfKnownKeysIsAccepted(): void
    {
        $allowlist = ['tools' => null];

        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => $allowlist]), $this->context);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([[['id' => $this->userId, 'mcpAllowlist' => $allowlist]]], $this->repository->updates);
    }

    public function testUserNotFound(): void
    {
        $repository = StaticEntityRepository::of(UserCollection::class, [[]]);

        $controller = new UserMcpAllowlistController($repository);

        $response = $controller->save(Uuid::randomHex(), $this->makeRequest(['allowlist' => null]), $this->context);

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertSame([], $repository->updates);
    }

    public function testMissingAllowlistKeyReturnsBadRequest(): void
    {
        $response = $this->controller->save($this->userId, $this->makeRequest([]), $this->context);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $this->repository->updates);
    }

    public function testInvalidAllowlistTypeReturnsBadRequest(): void
    {
        $response = $this->controller->save($this->userId, $this->makeRequest(['allowlist' => 'not-an-array']), $this->context);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $this->repository->updates);
    }

    public function testInvalidAllowlistWithNonStringToolsReturnsBadRequest(): void
    {
        $response = $this->controller->save(
            $this->userId,
            $this->makeRequest(['allowlist' => ['tools' => [1, 2, 3], 'resources' => null, 'prompts' => null]]),
            $this->context,
        );

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $this->repository->updates);
    }

    public function testInvalidAllowlistWithNonStringResourcesReturnsBadRequest(): void
    {
        $response = $this->controller->save(
            $this->userId,
            $this->makeRequest(['allowlist' => ['tools' => null, 'resources' => [true, false], 'prompts' => null]]),
            $this->context,
        );

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $this->repository->updates);
    }

    public function testAllowlistWithUnknownKeyReturnsBadRequest(): void
    {
        $response = $this->controller->save(
            $this->userId,
            $this->makeRequest(['allowlist' => ['tools' => null, 'unknownKey' => 'value']]),
            $this->context,
        );

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $this->repository->updates);
    }

    public function testInvalidAllowlistWithNonArrayTypeValueReturnsBadRequest(): void
    {
        $response = $this->controller->save(
            $this->userId,
            $this->makeRequest(['allowlist' => ['tools' => null, 'resources' => null, 'prompts' => 'not-valid']]),
            $this->context,
        );

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $this->repository->updates);
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
}
