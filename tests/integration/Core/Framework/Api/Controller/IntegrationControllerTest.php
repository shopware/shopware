<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    /**
     * @var EntityRepository<IntegrationCollection>
     */
    private EntityRepository $repository;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('integration.repository');
    }

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

        $client->jsonRequest('POST', '/api/integration', $data);

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

        $this->repository->create([$integration], $context);

        $client = $this->getBrowser();

        $client->jsonRequest(
            'PATCH',
            '/api/integration/' . $ids->get('integration'),
            ['admin' => true]
        );

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $assigned = $this->repository->search(new Criteria([$ids->get('integration')]), $context);

        static::assertCount(1, $assigned);
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

        $client->jsonRequest('POST', '/api/integration', $data);

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

        $client->jsonRequest('POST', '/api/integration', $data);

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

        $client->jsonRequest('POST', '/api/integration', $data);

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

        $this->repository->create([$integration], $context);

        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], ['integration:update']);
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

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $criteria = new Criteria([$ids->get('integration')]);
        $criteria->addAssociation('aclRoles');

        $assigned = $this->repository->search($criteria, $context);

        static::assertNotNull($assigned->first());
        static::assertNotNull($assigned->first()->getAclRoles());

        $aclRoleIds = array_values($assigned->first()->getAclRoles()->getIds());
        $expectedIds = $ids->getList(['role-1', 'role-2']);
        sort($expectedIds);

        static::assertSame($expectedIds, $aclRoleIds);
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

        $this->repository->create([$integration], $context);

        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], ['integration:create']);
        $client = $this->getBrowser();

        $client->jsonRequest(
            'PATCH',
            '/api/integration/' . $ids->get('integration'),
            ['admin' => true]
        );

        $response = $client->getResponse();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());

        $assigned = $this->repository->search(new Criteria([$ids->get('integration')]), $context);

        static::assertCount(1, $assigned);
        static::assertNotNull($assigned->first());
        static::assertFalse($assigned->first()->getAdmin());
    }
}
