<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\Scope\IntegrationVerifiedScope;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@framework')]
class IntegrationControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function tearDown(): void
    {
        $this->resetBrowser();
    }

    public function testCreateIntegration(): void
    {
        $client = $this->getBrowser(true, [IntegrationVerifiedScope::IDENTIFIER]);
        $data = [
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ];

        $client->jsonRequest('POST', '/api/integration', $data);

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testCreateIntegrationWithAdministratorRole(): void
    {
        $client = $this->getBrowser(true, [IntegrationVerifiedScope::IDENTIFIER]);

        $data = [
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            'admin' => true,
        ];

        $client->jsonRequest('POST', '/api/integration', $data);

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testUpdateIntegration(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $integration = [
            'id' => $ids->get('integration'),
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            'admin' => false,
        ];

        static::getContainer()->get('integration.repository')
            ->create([$integration], $context);

        $client = $this->getBrowser(true, [IntegrationVerifiedScope::IDENTIFIER]);

        $client->jsonRequest(
            'PATCH',
            '/api/integration/' . $ids->get('integration'),
            ['admin' => true]
        );

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        /** @var EntitySearchResult<IntegrationCollection> $assigned */
        $assigned = static::getContainer()->get('integration.repository')
            ->search(new Criteria([$ids->get('integration')]), $context);

        static::assertCount(1, $assigned->getEntities());
        $integration = $assigned->getEntities()->first();
        static::assertNotNull($integration);
        static::assertTrue($integration->getAdmin());
    }

    public function testPreventCreateIntegrationWithoutPermissions(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [IntegrationVerifiedScope::IDENTIFIER], []);
        $client = $this->getBrowser();

        $data = [
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ];

        $client->jsonRequest('POST', '/api/integration', $data);

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testCreateIntegrationWithPermissionsAsNonAdmin(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [IntegrationVerifiedScope::IDENTIFIER], ['integration:create']);
        $client = $this->getBrowser();

        $data = [
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ];

        $client->jsonRequest('POST', '/api/integration', $data);

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testPreventCreateIntegrationWithAdministratorRole(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [IntegrationVerifiedScope::IDENTIFIER], ['integration:update']);
        $client = $this->getBrowser();

        $data = [
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            'admin' => true,
        ];

        $client->jsonRequest('POST', '/api/integration', $data);

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPreventUpdateIntegrationRolesAsNonAdmin(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $integration = [
            'id' => $ids->get('integration'),
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            'admin' => false,
        ];

        static::getContainer()->get('integration.repository')
            ->create([$integration], $context);

        $this->authorizeBrowser($this->getBrowser(), [IntegrationVerifiedScope::IDENTIFIER], ['integration:update']);
        $client = $this->getBrowser();

        $client->jsonRequest(
            'PATCH',
            '/api/integration/' . $ids->get('integration'),
            [
                'aclRoles' => [
                    ['id' => $ids->get('role-1'), 'name' => 'role-1'],
                    ['id' => $ids->get('role-2'), 'name' => 'role-2'],
                ],
            ]
        );

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPreventUpdateIntegrationWithAdministratorRoleAsNonAdmin(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        $integration = [
            'id' => $ids->get('integration'),
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            'admin' => false,
        ];

        static::getContainer()->get('integration.repository')
            ->create([$integration], $context);

        $this->authorizeBrowser($this->getBrowser(), [IntegrationVerifiedScope::IDENTIFIER], ['integration:create']);
        $client = $this->getBrowser();

        $client->jsonRequest(
            'PATCH',
            '/api/integration/' . $ids->get('integration'),
            ['admin' => true]
        );

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        /** @var EntitySearchResult<IntegrationCollection> $assigned */
        $assigned = static::getContainer()->get('integration.repository')
            ->search(new Criteria([$ids->get('integration')]), $context);

        static::assertCount(1, $assigned->getEntities());
        $integration = $assigned->getEntities()->first();
        static::assertNotNull($integration);
        static::assertFalse($integration->getAdmin());
    }

    public function testCreateIntegrationRequiresIntegrationVerifiedScope(): void
    {
        $client = $this->getBrowser(true, [UserVerifiedScope::IDENTIFIER]);

        $client->jsonRequest('POST', '/api/integration', [
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        static::assertIsArray($content);
        static::assertSame(
            'This access token does not have the scope "integration-verified" to process this Request',
            $content['errors'][0]['detail']
        );
    }

    public function testIntegrationClientCanCreateIntegrationWithoutVerifiedScope(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        static::getContainer()->get('integration.repository')->create([[
            'id' => $ids->get('integration'),
            'label' => 'admin integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            'admin' => true,
        ]], $context);

        $client = $this->createClient(authorized: false);
        $this->authorizeBrowserWithIntegration($client, $ids->get('integration'));

        $client->jsonRequest('POST', '/api/integration', [
            'label' => 'created by integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testDeleteIntegrationRequiresIntegrationVerifiedScope(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('integration.repository')->create([[
            'id' => $ids->get('integration'),
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]], Context::createDefaultContext());

        $client = $this->getBrowser(true, [UserVerifiedScope::IDENTIFIER]);
        $client->jsonRequest('DELETE', '/api/integration/' . $ids->get('integration'));

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true);
        static::assertIsArray($content);
        static::assertSame(
            'This access token does not have the scope "integration-verified" to process this Request',
            $content['errors'][0]['detail']
        );
    }

    public function testDeleteIntegration(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('integration.repository')->create([[
            'id' => $ids->get('integration'),
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]], Context::createDefaultContext());

        $client = $this->getBrowser(true, [IntegrationVerifiedScope::IDENTIFIER]);
        $client->jsonRequest('DELETE', '/api/integration/' . $ids->get('integration'));

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testIntegrationClientCanDeleteIntegrationWithoutVerifiedScope(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        static::getContainer()->get('integration.repository')->create([
            [
                'id' => $ids->get('actor'),
                'label' => 'admin integration',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
                'admin' => true,
            ],
            [
                'id' => $ids->get('target'),
                'label' => 'integration to delete',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            ],
        ], $context);

        $client = $this->createClient(authorized: false);
        $this->authorizeBrowserWithIntegration($client, $ids->get('actor'));

        $client->jsonRequest('DELETE', '/api/integration/' . $ids->get('target'));

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode(), (string) $client->getResponse()->getContent());
    }

    public function testSyncApiCannotWriteIntegrations(): void
    {
        $client = $this->getBrowser(true, [IntegrationVerifiedScope::IDENTIFIER]);
        $client->jsonRequest('POST', '/api/_action/sync', [[
            'key' => 'write-integration',
            'action' => 'upsert',
            'entity' => 'integration',
            'payload' => [[
                'id' => (new IdsCollection())->get('synced'),
                'label' => 'synced integration',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            ]],
        ]]);

        $response = $client->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString('integration', (string) $response->getContent());
    }
}
