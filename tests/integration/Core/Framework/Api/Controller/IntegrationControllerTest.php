<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\Integration\IntegrationEntity;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('services-settings')]
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

        $this->getContainer()->get('integration.repository')
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
        $assigned = $this->getContainer()->get('integration.repository')
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

    public function testUpdateIntegrationRolesAsNonAdmin(): void
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

        $this->getContainer()->get('integration.repository')
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

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $criteria = new Criteria([$ids->get('integration')]);
        $criteria->addAssociation('aclRoles');

        /** @var IntegrationCollection|IntegrationEntity[] $assigned */
        $assigned = $this->getContainer()->get('integration.repository')
            ->search($criteria, $context);

        static::assertNotNull($assigned->first());
        static::assertNotNull($assigned->first()->getAclRoles());

        $aclRoleIds = array_values($assigned->first()->getAclRoles()->getIds());
        $expectedIds = $ids->getList(['role-1', 'role-2']);
        sort($expectedIds);

        static::assertEquals($expectedIds, $aclRoleIds);
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

        $this->getContainer()->get('integration.repository')
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
        $assigned = $this->getContainer()->get('integration.repository')
            ->search(new Criteria([$ids->get('integration')]), $context);

        static::assertEquals(1, $assigned->count());
        static::assertNotNull($assigned->first());
        static::assertFalse($assigned->first()->getAdmin());
    }
}
