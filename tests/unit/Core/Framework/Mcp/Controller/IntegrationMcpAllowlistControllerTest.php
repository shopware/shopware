<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Mcp\Controller\IntegrationMcpAllowlistController;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Integration\IntegrationEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(IntegrationMcpAllowlistController::class)]
class IntegrationMcpAllowlistControllerTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['MCP_SERVER'] = '1';
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

            $controller = new IntegrationMcpAllowlistController($repository);
            $response = $controller->save('some-id', $this->makeRequest(['allowlist' => null]), Context::createDefaultContext());

            static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        } finally {
            $_SERVER['MCP_SERVER'] = '1';
        }
    }

    public function testSaveStructuredAllowlist(): void
    {
        $integrationId = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);

        $allowlist = [
            'tools' => ['shopware-entity-read', 'shopware-entity-search'],
            'resources' => ['shopware://entities'],
            'prompts' => null,
        ];

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$integration]));
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $integrationId, 'mcpAllowlist' => $allowlist]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testSaveAllowlistNull(): void
    {
        $integrationId = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$integration]));
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $integrationId, 'mcpAllowlist' => null]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => null]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testSaveAllowlistWithAllNullTypes(): void
    {
        $integrationId = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);

        $allowlist = ['tools' => null, 'resources' => null, 'prompts' => null];

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$integration]));
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $integrationId, 'mcpAllowlist' => $allowlist]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testSaveAllowlistWithEmptyArraysDeniesAll(): void
    {
        $integrationId = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);

        $allowlist = ['tools' => [], 'resources' => [], 'prompts' => []];

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$integration]));
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $integrationId, 'mcpAllowlist' => $allowlist]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testIntegrationNotFound(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([]));
        $repository->expects($this->never())->method('update');

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => null]);

        $response = $controller->save(Uuid::randomHex(), $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testMissingAllowlistKeyReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$integration]));
        $repository->expects($this->never())->method('update');

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest([]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistTypeReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$integration]));
        $repository->expects($this->never())->method('update');

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => 'not-an-array']);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistWithNonStringToolsReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$integration]));
        $repository->expects($this->never())->method('update');

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => ['tools' => [1, 2, 3], 'resources' => null, 'prompts' => null]]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistWithNonStringResourcesReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$integration]));
        $repository->expects($this->never())->method('update');

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => ['tools' => null, 'resources' => [true, false], 'prompts' => null]]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testAllowlistWithSubsetOfKnownKeysIsAccepted(): void
    {
        $integrationId = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);

        $allowlist = ['tools' => null];

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$integration]));
        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $integrationId, 'mcpAllowlist' => $allowlist]]);

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => $allowlist]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAllowlistWithUnknownKeyReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$integration]));
        $repository->expects($this->never())->method('update');

        $controller = new IntegrationMcpAllowlistController($repository);
        $request = $this->makeRequest(['allowlist' => ['tools' => null, 'unknownKey' => 'value']]);

        $response = $controller->save($integrationId, $request, Context::createDefaultContext());

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testInvalidAllowlistWithNonArrayTypeValueReturnsBadRequest(): void
    {
        $integrationId = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setId($integrationId);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeSearchResult([$integration]));
        $repository->expects($this->never())->method('update');

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

    /**
     * @param list<IntegrationEntity> $entities
     *
     * @return EntitySearchResult<IntegrationCollection>
     */
    private function makeSearchResult(array $entities): EntitySearchResult
    {
        $collection = new IntegrationCollection($entities);

        return new EntitySearchResult(
            'integration',
            \count($entities),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
