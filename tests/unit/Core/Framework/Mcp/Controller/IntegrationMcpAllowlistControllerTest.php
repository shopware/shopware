<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Controller\IntegrationMcpAllowlistController;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IntegrationMcpAllowlistController::class)]
class IntegrationMcpAllowlistControllerTest extends TestCase
{
    public function testSaveStructuredAllowlist(): void
    {
        $integrationId = Uuid::randomHex();

        $allowlist = [
            'tools' => ['shopware-entity-read', 'shopware-entity-search'],
            'resources' => ['shopware://entities'],
            'prompts' => null,
        ];

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([[['id' => $integrationId, 'mcpAllowlist' => $allowlist]]], $repository->updates);
    }

    public function testSaveAllowlistNull(): void
    {
        $integrationId = Uuid::randomHex();

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => null]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([[['id' => $integrationId, 'mcpAllowlist' => null]]], $repository->updates);
    }

    public function testSaveAllowlistWithAllNullTypes(): void
    {
        $integrationId = Uuid::randomHex();

        $allowlist = ['tools' => null, 'resources' => null, 'prompts' => null];

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([[['id' => $integrationId, 'mcpAllowlist' => $allowlist]]], $repository->updates);
    }

    public function testSaveAllowlistWithEmptyArraysDeniesAll(): void
    {
        $integrationId = Uuid::randomHex();

        $allowlist = ['tools' => [], 'resources' => [], 'prompts' => []];

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([[['id' => $integrationId, 'mcpAllowlist' => $allowlist]]], $repository->updates);
    }

    public function testIntegrationNotFound(): void
    {
        $repository = new StaticEntityRepository([[]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => null]);

        $response = $controller->save(Uuid::randomHex(), $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertSame([], $repository->updates);
    }

    public function testMissingAllowlistKeyReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest([]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $repository->updates);
    }

    public function testInvalidAllowlistTypeReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => 'not-an-array']);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $repository->updates);
    }

    public function testInvalidAllowlistWithNonStringToolsReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => ['tools' => [1, 2, 3], 'resources' => null, 'prompts' => null]]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $repository->updates);
    }

    public function testInvalidAllowlistWithNonStringResourcesReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => ['tools' => null, 'resources' => [true, false], 'prompts' => null]]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $repository->updates);
    }

    public function testRouteRequiresBothTheActionAndTheEntityPrivilege(): void
    {
        // Mirrors UserMcpAllowlistController. AclWriteValidator already enforced integration:update
        // on the write; naming it on the route turns a DAL exception into a plain 403.
        $route = (new AttributeRouteControllerLoader())->load(IntegrationMcpAllowlistController::class)->get('api.action.integration.mcp-allowlist');

        static::assertNotNull($route);
        static::assertSame(['api_action_integration_mcp-allowlist', 'integration:update'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    public function testObjectShapedPerTypeValueIsRejected(): void
    {
        $integrationId = Uuid::randomHex();

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        // A JSON object here would be stored but read back as an empty selection, so reject it
        // instead of silently persisting something that grants nothing.
        $request = $this->makeRequest(['allowlist' => ['tools' => ['x' => 'shopware-entity-delete']]]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $repository->updates);
    }

    public function testAllowlistWithSubsetOfKnownKeysIsAccepted(): void
    {
        $integrationId = Uuid::randomHex();

        $allowlist = ['tools' => null];

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame([[['id' => $integrationId, 'mcpAllowlist' => $allowlist]]], $repository->updates);
    }

    public function testAllowlistWithUnknownKeyReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => ['tools' => null, 'unknownKey' => 'value']]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame([], $repository->updates);
    }

    public function testInvalidAllowlistWithNonArrayTypeValueReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();

        $repository = new StaticEntityRepository([[$integrationId]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => ['tools' => null, 'resources' => null, 'prompts' => 'not-valid']]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

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
}
