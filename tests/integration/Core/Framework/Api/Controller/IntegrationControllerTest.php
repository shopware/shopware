<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\System\Integration\IntegrationException;
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

    #[Group('slow')]
    public function testCreateIntegration(): void
    {
        $client = $this->getBrowser();
        $data = [
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ];

        $client->request('POST', '/api/integration', [], [], [], \json_encode($data, \JSON_THROW_ON_ERROR));

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testCreateIntegrationWithAdministratorRole(): void
    {
        $client = $this->getBrowser();

        $data = [
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            'admin' => true,
        ];

        $client->request('POST', '/api/integration', [], [], [], \json_encode($data, \JSON_THROW_ON_ERROR));

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

        $client = $this->getBrowser();

        $json = \json_encode(['admin' => true], \JSON_THROW_ON_ERROR);
        static::assertIsString($json);

        $client->request(
            'PATCH',
            '/api/integration/' . $ids->get('integration'),
            [],
            [],
            [],
            $json
        );

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        /** @var IntegrationCollection|IntegrationEntity[] $assigned */
        $assigned = static::getContainer()->get('integration.repository')
            ->search(new Criteria([$ids->get('integration')]), $context);

        static::assertNotNull($assigned);
        static::assertEquals(1, $assigned->count());
        static::assertNotNull($assigned->first());
        static::assertTrue($assigned->first()->getAdmin());
    }

    public function testPreventCreateIntegrationWithoutPermissions(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], []);
        $client = $this->getBrowser();

        $data = [
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ];

        $client->request('POST', '/api/integration', [], [], [], \json_encode($data, \JSON_THROW_ON_ERROR));

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testCreateIntegrationWithPermissionsAsNonAdmin(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], ['integration:create']);
        $client = $this->getBrowser();

        $data = [
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ];

        $client->request('POST', '/api/integration', [], [], [], \json_encode($data, \JSON_THROW_ON_ERROR));

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testPreventCreateIntegrationWithAdministratorRole(): void
    {
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], ['integration:update']);
        $client = $this->getBrowser();

        $data = [
            'label' => 'integration',
            'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
            'admin' => true,
        ];

        $client->request('POST', '/api/integration', [], [], [], \json_encode($data, \JSON_THROW_ON_ERROR));

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

        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], ['integration:update']);
        $client = $this->getBrowser();

        $json = \json_encode(
            [
                'aclRoles' => [
                    ['id' => $ids->get('role-1'), 'name' => 'role-1'],
                    ['id' => $ids->get('role-2'), 'name' => 'role-2'],
                ],
            ],
            \JSON_THROW_ON_ERROR
        );
        static::assertIsString($json);

        $client->request(
            'PATCH',
            '/api/integration/' . $ids->get('integration'),
            [],
            [],
            [],
            $json
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

        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], ['integration:create']);
        $client = $this->getBrowser();

        $json = \json_encode(['admin' => true], \JSON_THROW_ON_ERROR);
        static::assertIsString($json);

        $client->request(
            'PATCH',
            '/api/integration/' . $ids->get('integration'),
            [],
            [],
            [],
            $json
        );

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        /** @var IntegrationCollection|IntegrationEntity[] $assigned */
        $assigned = static::getContainer()->get('integration.repository')
            ->search(new Criteria([$ids->get('integration')]), $context);

        static::assertEquals(1, $assigned->count());
        static::assertNotNull($assigned->first());
        static::assertFalse($assigned->first()->getAdmin());
    }

    public function testPreventUpdateAndDeleteOfAdminIntegrationAsNonAdmin(): void
    {
        $integrationId = $this->createIntegration(admin: true);
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], ['integration:update', 'integration:delete']);

        $this->getBrowser()->jsonRequest('PATCH', '/api/integration/' . $integrationId, [
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);
        $this->assertAdminIntegrationChangeNotAllowed();

        $this->getBrowser()->jsonRequest('DELETE', '/api/integration/' . $integrationId);
        $this->assertAdminIntegrationChangeNotAllowed();
    }

    public function testUpdateRegularIntegrationAsNonAdmin(): void
    {
        $integrationId = $this->createIntegration(admin: false);
        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], ['integration:update']);

        $this->getBrowser()->jsonRequest('PATCH', '/api/integration/' . $integrationId, [
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
    }

    private function createIntegration(bool $admin): string
    {
        $id = Uuid::randomHex();
        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id, $admin): void {
            static::getContainer()->get('integration.repository')->create([[
                'id' => $id,
                'label' => 'integration',
                'accessKey' => AccessKeyHelper::generateAccessKey('integration'),
                'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
                'admin' => $admin,
            ]], $context);
        });

        return $id;
    }

    private function assertAdminIntegrationChangeNotAllowed(): void
    {
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
        static::assertStringContainsString(IntegrationException::ADMIN_INTEGRATION_CHANGE_NOT_ALLOWED, (string) $response->getContent());
    }
}
