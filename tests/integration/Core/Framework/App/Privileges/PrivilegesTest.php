<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Privileges;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppException;
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

        $setPrivileges = ['customer:read', 'customer:update', 'product:read', 'product:update'];
        $this->privileges->setPrivileges($appId, $setPrivileges, $context);

        $this->assertPrivileges(
            'TestApp',
            $setPrivileges,
            []
        );

        $this->privileges->revokeAllForApps([$appId], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            $setPrivileges,
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

    public function testUpdatePrivilegesAcceptOnly(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            ['customer:read', 'customer:update'],
        );

        $this->privileges->updatePrivileges($appId, ['customer:update'], [], $context);

        $this->assertPrivileges(
            'TestApp',
            ['customer:update'],
            ['customer:read'],
        );
    }

    public function testUpdatePrivilegesRevokeOnly(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['customer:read', 'customer:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['customer:read', 'customer:update'],
            [],
        );

        $this->privileges->updatePrivileges($appId, [], ['customer:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['customer:read'],
            ['customer:update'],
        );
    }

    public function testUpdatePrivilegesBoth(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['customer:read'], $context);
        $this->privileges->requestPrivileges($appId, ['product:read', 'product:update'], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            ['product:read', 'product:update'],
        );

        $this->privileges->updatePrivileges($appId, ['product:read'], ['customer:read'], $context);

        // - product:read is accepted (moved from requested to existing)
        // - customer:read is revoked (it was not active, so no change to requested based on this revoke)
        // - product:update remains in requested
        $this->assertPrivileges(
            'TestApp',
            ['product:read'],
            ['product:update'],
        );
    }

    public function testUpdatePrivilegesThrowsExceptionOnConflict(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update'], $context);

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('A privilege cannot be present in both the accept and revoke lists simultaneously.');

        $this->privileges->updatePrivileges($appId, ['customer:read'], ['customer:read'], $context);
    }

    public function testUpdatePrivilegesNoChanges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['customer:read'], $context);
        $this->privileges->requestPrivileges($appId, ['product:read'], $context);

        $this->assertPrivileges(
            'TestApp',
            [],
            ['product:read'],
        );

        $this->privileges->updatePrivileges($appId, [], [], $context);
        $this->assertPrivileges(
            'TestApp',
            [],
            ['product:read'],
        );
    }

    public function testUpdatePrivilegesAcceptNonRequestedPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();
        $this->privileges->requestPrivileges($appId, ['customer:read'], $context);
        $this->assertPrivileges(
            'TestApp',
            [],
            ['customer:read'],
        );

        $this->privileges->updatePrivileges($appId, ['product:read'], [], $context);
        $this->assertPrivileges(
            'TestApp',
            [],
            ['customer:read'],
        );
    }

    public function testGetRequestedPrivilegesSingleApp(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->requestPrivileges($appId, ['customer:read', 'customer:update'], $context);

        static::assertSame(
            [
                $appId => ['customer:read', 'customer:update'],
            ],
            $this->privileges->getRequestedPrivileges([$appId])
        );
    }

    public function testGetRequestedPrivilegesMultiApp(): void
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
            $this->privileges->getRequestedPrivileges([$appId1, $appId2])
        );
    }

    public function testGetPrivilegesSingleApp(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['customer:read', 'customer:update'], $context);

        static::assertSame(
            [
                $appId => ['customer:read', 'customer:update'],
            ],
            $this->privileges->getPrivileges([$appId])
        );
    }

    public function testGetPrivilegesMultiApp(): void
    {
        $appId1 = $this->createApp();
        $appId2 = $this->createApp('App2');
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId1, ['customer:read', 'customer:update'], $context);
        $this->privileges->setPrivileges($appId2, ['product:read', 'product:update'], $context);

        static::assertSame(
            [
                $appId1 => ['customer:read', 'customer:update'],
                $appId2 => ['product:read', 'product:update'],
            ],
            $this->privileges->getPrivileges([$appId1, $appId2])
        );
    }

    public function testGetRequestedPrivilegesForAllApps(): void
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
            $this->privileges->getRequestedPrivilegesForAllApps()
        );
    }

    public function testUpdatePrivilegesRevokeNonExistentPrivileges(): void
    {
        $appId = $this->createApp();
        $context = Context::createDefaultContext();

        $this->privileges->setPrivileges($appId, ['customer:read'], $context);

        $this->assertPrivileges(
            'TestApp',
            ['customer:read'],
            [],
        );

        $this->privileges->updatePrivileges($appId, [], ['product:read'], $context);
        $this->assertPrivileges(
            'TestApp',
            ['customer:read'],
            [],
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
