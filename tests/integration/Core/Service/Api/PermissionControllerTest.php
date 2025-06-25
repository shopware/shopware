<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Service\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Service\PermissionsService;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class PermissionControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use IntegrationTestBehaviour;

    private PermissionsService $permissionsService;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->permissionsService = $this->getContainer()->get(PermissionsService::class);
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
    }

    protected function tearDown(): void
    {
        $this->systemConfigService->delete('core.services.acceptedPermissionsRevision');
    }

    public function testGrantPermissionsEndpoint(): void
    {
        $revision = '2025-06-13';
        $storedRevision = $this->permissionsService->getAcceptedPermissionsRevision();
        static::assertNull($storedRevision, 'No revision should be stored before granting permissions');

        $this->getBrowser()->request(
            'POST',
            '/api/services/permissions/grant/' . $revision,
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{}', $response->getContent());
        $storedRevision = $this->permissionsService->getAcceptedPermissionsRevision();
        static::assertNotNull($storedRevision);
        static::assertSame($revision, $storedRevision->format('Y-m-d'));
    }

    public function testGrantPermissionsEndpointWithInvalidRevision(): void
    {
        $invalidRevision = 'invalid-date';

        $this->getBrowser()->request(
            'POST',
            '/api/services/permissions/grant/' . $invalidRevision,
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $responseData);
        static::assertSame('SERVICE__INVALID_PERMISSIONS_REVISION_FORMAT', $responseData['errors'][0]['code']);
        static::assertStringContainsString('invalid-date', $responseData['errors'][0]['detail']);

        $storedRevision = $this->permissionsService->getAcceptedPermissionsRevision();
        static::assertNull($storedRevision);
    }

    public function testRevokePermissionsEndpoint(): void
    {
        $revision = '2025-06-13';
        $this->permissionsService->grantPermissions($revision, Context::createDefaultContext());
        static::assertNotNull($this->permissionsService->getAcceptedPermissionsRevision());

        $this->getBrowser()->request(
            'POST',
            '/api/services/permissions/revoke',
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ]
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{}', $response->getContent());
        $storedRevision = $this->permissionsService->getAcceptedPermissionsRevision();
        static::assertNull($storedRevision);
    }
}
