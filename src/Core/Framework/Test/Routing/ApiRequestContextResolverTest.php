<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\ApiRequestContextResolver;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

class ApiRequestContextResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ApiRequestContextResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->resolver = $this->getContainer()->get(ApiRequestContextResolver::class);
    }

    /**
     * @dataProvider userRoleProvider
     */
    public function testResolveAdminSourceByOAuthUserId(array $expected, array $roles, bool $isAdmin = false): void
    {
        $user = $this->createUser($roles, $isAdmin);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_USER_ID, $user->getUserId());
        $request->attributes->set('_routeScope', new RouteScope(['scopes' => ['api']]));
        $this->resolver->resolve($request);

        static::assertTrue(
            $request->attributes->has(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)
        );

        /** @var Context $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        static::assertInstanceOf(AdminApiSource::class, $context->getSource());

        /** @var AdminApiSource $source */
        $source = $context->getSource();

        static::assertEquals($isAdmin, $source->isAdmin());

        foreach ($expected as $privilege => $allowed) {
            static::assertEquals($allowed, $source->isAllowed($privilege), $privilege);
        }
    }

    /**
     * @dataProvider userRoleProvider
     */
    public function testResolveContextByClientId(array $expected, array $roles, bool $isAdmin = false): void
    {
        $user = $this->createUser($roles, $isAdmin);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, 'test');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $this->createAccessKey($user->getUserId()));

        $request->attributes->set('_routeScope', new RouteScope(['scopes' => ['api']]));
        $this->resolver->resolve($request);

        static::assertTrue(
            $request->attributes->has(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)
        );

        /** @var Context $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        static::assertInstanceOf(AdminApiSource::class, $context->getSource());

        /** @var AdminApiSource $source */
        $source = $context->getSource();

        static::assertEquals($isAdmin, $source->isAdmin());

        foreach ($expected as $privilege => $allowed) {
            static::assertEquals($allowed, $source->isAllowed($privilege), $privilege);
        }
    }

    public function testContextSkipTriggerFlowState(): void
    {
        $user = $this->createUser([], true);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, 'test');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $this->createAccessKey($user->getUserId()));
        $request->attributes->set('_routeScope', new RouteScope(['scopes' => ['api']]));

        $this->resolver->resolve($request);

        static::assertTrue(
            $request->attributes->has(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)
        );

        /** @var Context $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        static::assertFalse($context->hasState(Context::SKIP_TRIGGER_FLOW));

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_ACCESS_TOKEN_ID, 'test');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $this->createAccessKey($user->getUserId()));
        $request->attributes->set('_routeScope', new RouteScope(['scopes' => ['api']]));

        $request->headers->set(PlatformRequest::HEADER_SKIP_TRIGGER_FLOW, true);

        $this->resolver->resolve($request);

        static::assertTrue(
            $request->attributes->has(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT)
        );

        /** @var Context $context */
        $context = $request->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        static::assertTrue($context->hasState(Context::SKIP_TRIGGER_FLOW));
    }

    public function userRoleProvider()
    {
        return [
            [
                ['product:detail' => true, 'product:create' => true, 'product:delete' => false],
                ['product-creator' => ['product:detail', 'product:create']],
                false,
            ],

            // test admin
            [
                ['product:detail' => true, 'product:create' => true],
                [],
                true,
            ],

            // test multiple roles
            [
                [
                    'product:detail' => true,
                    'product:create' => true,
                    'media:detail' => true,
                    'media:create' => true,
                    'media:delete' => false,
                    'product:delete' => false,
                ],
                [
                    'product-creator' => ['product:detail', 'product:create'],
                    'media-admin' => ['media:detail', 'media:create'],
                ],
                false,
            ],

            // test no roles
            [
                [
                    'product:detail' => false,
                    'product:create' => false,
                    'media:detail' => false,
                    'media:create' => false,
                    'media:delete' => false,
                    'product:delete' => false,
                ],
                [],
                false,
            ],
        ];
    }

    public function testAdminIntegration(): void
    {
        $ids = new IdsCollection();
        $browser = $this->getBrowserAuthenticatedWithIntegration($ids->create('integration'));

        $this->getContainer()
            ->get(Connection::class)
            ->executeUpdate('UPDATE `integration` SET `admin` = 1 WHERE id = :id', ['id' => Uuid::fromHexToBytes($ids->get('integration'))]);

        $browser->request('POST', '/api/search/currency', [
            'limit' => 2,
        ]);
        $response = json_decode($browser->getResponse()->getContent(), true);

        static::assertEquals(200, $browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('data', $response);
    }

    public function testIntegrationWithoutPrivileges(): void
    {
        $ids = new IdsCollection();
        $browser = $this->getBrowserAuthenticatedWithIntegration($ids->create('integration'));

        $this->getContainer()
            ->get(Connection::class)
            ->executeUpdate('UPDATE `integration` SET `admin` = 0 WHERE id = :id', ['id' => Uuid::fromHexToBytes($ids->get('integration'))]);

        $browser->request('POST', '/api/search/currency', [
            'limit' => 2,
        ]);

        static::assertEquals(403, $browser->getResponse()->getStatusCode());

        $response = json_decode($browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        $errors = $response['errors'];
        static::assertEquals('{"message":"Missing privilege","missingPrivileges":["currency:read"]}', $errors[0]['detail']);
    }

    public function testIntegrationWithPrivileges(): void
    {
        $ids = new IdsCollection();
        $browser = $this->getBrowserAuthenticatedWithIntegration($ids->create('integration'));

        $this->getContainer()
            ->get(Connection::class)
            ->executeUpdate('UPDATE `integration` SET `admin` = 0 WHERE id = :id', ['id' => Uuid::fromHexToBytes($ids->get('integration'))]);

        $this->addRoleToIntegration($ids->get('integration'), ['currency:read']);

        $browser->request('POST', '/api/search/currency', [
            'limit' => 2,
        ]);
        $response = json_decode($browser->getResponse()->getContent(), true);

        static::assertEquals(200, $browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('data', $response);
    }

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    private function createUser(array $roles, bool $isAdmin): TestUser
    {
        $user = TestUser::createNewTestUser($this->connection);
        $this->connection->executeUpdate(
            'UPDATE `user` SET admin = :admin WHERE id = :id',
            ['admin' => $isAdmin ? 1 : 0, 'id' => Uuid::fromHexToBytes($user->getUserId())]
        );

        foreach ($roles as $role => $privs) {
            $id = Uuid::randomBytes();

            $this->connection->insert('acl_role', [
                'id' => $id,
                'name' => $role,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
                'privileges' => json_encode($privs),
            ]);

            $this->connection->insert('acl_user_role', [
                'user_id' => Uuid::fromHexToBytes($user->getUserId()),
                'acl_role_id' => $id,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            ]);
        }

        return $user;
    }

    private function createAccessKey(string $userId): string
    {
        $key = AccessKeyHelper::generateAccessKey('user');

        $data = [
            'userId' => $userId,
            'writeAccess' => true,
            'accessKey' => $key,
            'secretAccessKey' => AccessKeyHelper::generateSecretAccessKey(),
        ];

        $this->getContainer()->get('user_access_key.repository')
            ->create([$data], Context::createDefaultContext());

        return $key;
    }

    private function addRoleToIntegration(string $integrationId, array $privileges): void
    {
        $id = Uuid::randomHex();
        $role = ['id' => $id, 'name' => 'test', 'privileges' => $privileges];

        $this->getContainer()->get('acl_role.repository')
            ->create([$role], Context::createDefaultContext());

        $this->getContainer()->get(Connection::class)
            ->insert('integration_role', [
                'acl_role_id' => Uuid::fromHexToBytes($id),
                'integration_id' => Uuid::fromHexToBytes($integrationId),
            ]);
    }
}
