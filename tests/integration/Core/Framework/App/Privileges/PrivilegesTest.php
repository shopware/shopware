<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Privileges;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Privileges\Privileges;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class PrivilegesTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private Privileges $privileges;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->privileges = $this->getContainer()->get(Privileges::class);
        $this->appRepository = $this->getContainer()->get('app.repository');
    }

    public function testSetPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['customer:read', 'customer:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['customer:read', 'customer:update'],
            [],
        );
    }

    public function testRequestPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            ['customer:read', 'customer:update'],
        );
    }

    public function testRequestPrivilegesRemovesExistingPrivilegesNotIncludedInRequest(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['customer:read', 'customer:update'], $context);
        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:write'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['customer:read'],
            ['customer:write'],
        );
    }

    public function testRequestSamePrivilegesAsExisting(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['product:read', 'product:update'], $context);
        $this->privileges->requestPrivileges($appId, ['product:read', 'product:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['product:read', 'product:update'],
            [],
        );
    }

    public function testRevokeAllPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['product:read', 'product:update'], $context);
        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update', 'product:read', 'product:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['product:read', 'product:update'],
            ['customer:read', 'customer:update']
        );

        $this->privileges->revokeAllForApps([$appId], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            []
        );
    }

    public function testAcceptAllPrivilegesAcceptsRequestedPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            ['customer:read', 'customer:update']
        );

        $this->privileges->acceptAllForApps([$appId], $context);

        $this->assertPrivileges(
            'TestApp',
            ['customer:read', 'customer:update'],
            [],
        );
    }

    public function testAcceptOnlyPrivilegesAcceptsSpecifiedRequestedPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            ['customer:read', 'customer:update'],
        );

        $this->privileges->acceptOnly($appId, ['customer:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['customer:update'],
            ['customer:read'],
        );
    }

    public function testGetPendingPrivilegesSingleApp(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update'], $context);

        static::assertSame(
            [
                $appId => ['customer:read', 'customer:update'],
            ],
            $this->privileges->getPendingPrivileges([$appId])
        );
    }

    public function testGetPendingPrivilegesMultiApp(): void
    {
        $appId1 = $this->createApp();
        $appId2 = $this->createApp('App2');
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId1, ['customer:read', 'customer:update'], $context);
        $this->privileges->requestPrivileges($appId2, ['product:read', 'product:update'], $context);

        static::assertSame(
            [
                $appId1 => ['customer:read', 'customer:update'],
                $appId2 => ['product:read', 'product:update'],
            ],
            $this->privileges->getPendingPrivileges([$appId1, $appId2])
        );
    }

    public function testGetPendingPrivilegesForAllApps(): void
    {
        $appId1 = $this->createApp();
        $appId2 = $this->createApp('App2');
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId1, ['customer:read', 'customer:update'], $context);
        $this->privileges->requestPrivileges($appId2, ['product:read', 'product:update'], $context);

        static::assertSame(
            [
                'TestApp' => ['customer:read', 'customer:update'],
                'App2' => ['product:read', 'product:update'],
            ],
            $this->privileges->getPendingPrivilegesForAllApps()
        );
    }

    /**
     * @param list<string> $expectedPrivileges
     * @param list<string> $expectedRequestedPrivileges
     */
    private function assertPrivileges(
        string $appName,
        array $expectedPrivileges,
        array $expectedRequestedPrivileges
    ): void {
        $privileges = $this->connection->fetchAllAssociative(
            <<<'SQL'
                SELECT acl_role.privileges as privileges, app.requested_privileges as requested_privileges
                FROM app
                INNER JOIN acl_role ON (acl_role.id = app.acl_role_id)
                WHERE app.name = :name
            SQL,
            ['name' => $appName]
        );

        static::assertCount(1, $privileges);

        static::assertSame($expectedPrivileges, json_decode($privileges[0]['privileges'], true, \JSON_THROW_ON_ERROR));
        static::assertSame($expectedRequestedPrivileges, json_decode($privileges[0]['requested_privileges'], true, \JSON_THROW_ON_ERROR));
    }

    private function createApp(string $name = 'TestApp'): string
    {
        $id = Uuid::randomHex();
        $app = [
            'id' => $id,
            'name' => $name,
            'active' => true,
            'path' => __DIR__,
            'version' => '0.0.1',
            'label' => 'test',
            'accessToken' => 'test',
            'appSecret' => 's3cr3t',
            'integration' => [
                'label' => 'test',
                'accessKey' => 'api access key',
                'secretAccessKey' => 'test',
            ],
            'aclRole' => [
                'name' => $name,
            ],
        ];

        $this->appRepository->create([$app], Context::createDefaultContext());

        return $id;
    }
}
