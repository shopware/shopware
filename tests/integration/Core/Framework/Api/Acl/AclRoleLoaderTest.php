<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Acl;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclRoleLoader;
use Shopware\Core\Framework\Api\Acl\AclRoles;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Store\ExtensionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestIntegration;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class AclRoleLoaderTest extends TestCase
{
    use ExtensionBehaviour;
    use IntegrationTestBehaviour;

    private Connection $connection;

    private AclRoleLoader $loader;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->loader = static::getContainer()->get(AclRoleLoader::class);
    }

    public function testAnAdminUserIsUnrestricted(): void
    {
        $adminId = $this->connection->fetchOne('SELECT LOWER(HEX(id)) FROM user WHERE username = :username', ['username' => 'admin']);
        static::assertIsString($adminId);

        static::assertEquals([$adminId => new AclRoles(true, [])], $this->loader->getRoles([$adminId]));
    }

    public function testANonAdminUserHasItsRoles(): void
    {
        $user = TestUser::createNewTestUser($this->connection, ['product:read']);

        static::assertEquals(
            [$user->getUserId() => new AclRoles(false, [(string) $user->getAclRoleId()])],
            $this->loader->getRoles([$user->getUserId()])
        );
    }

    public function testAnAdminIntegrationIsUnrestricted(): void
    {
        $integrationId = TestIntegration::createAdmin($this->connection)->getId();

        static::assertEquals([$integrationId => new AclRoles(true, [])], $this->loader->getRoles([$integrationId]));
    }

    public function testANonAdminIntegrationHasItsRoles(): void
    {
        $integration = TestIntegration::create($this->connection, ['product:read']);

        static::assertEquals(
            [$integration->getId() => new AclRoles(false, [(string) $integration->getAclRoleId()])],
            $this->loader->getRoles([$integration->getId()])
        );
    }

    public function testAnAppIntegrationOnlyHasTheAppsRole(): void
    {
        $this->installApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');
        $row = $this->connection->fetchNumeric('SELECT integration_id, acl_role_id FROM app WHERE name = :name', ['name' => 'minimal']);
        static::assertIsArray($row);
        [$integrationId, $appRoleId] = $row;

        $foreignRoleId = $this->createAclRole(['order:read']);
        $this->connection->insert('integration_role', ['integration_id' => $integrationId, 'acl_role_id' => Uuid::fromHexToBytes($foreignRoleId)]);
        $this->connection->update('integration', ['admin' => 1], ['id' => $integrationId]);

        static::assertEquals(
            [Uuid::fromBytesToHex($integrationId) => new AclRoles(false, [Uuid::fromBytesToHex($appRoleId)])],
            $this->loader->getRoles([Uuid::fromBytesToHex($integrationId)])
        );

        $this->removeApp(__DIR__ . '/../../App/Manifest/_fixtures/minimal');
    }

    public function testUnknownIdsAreLeftOut(): void
    {
        static::assertSame([], $this->loader->getRoles([Uuid::randomHex()]));
    }

    public function testGetPrivilegesForRoles(): void
    {
        $aclRoleId = $this->createAclRole(['customer:read', 'customer:create', 'category:read']);

        $privileges = $this->loader->getPrivilegesForRoles([$aclRoleId]);

        static::assertCount(1, $privileges);
        static::assertArrayHasKey($aclRoleId, $privileges);
        static::assertTrue($privileges[$aclRoleId]->isAllowed('customer', 'read'));
        static::assertTrue($privileges[$aclRoleId]->isAllowed('customer', 'create'));
        static::assertTrue($privileges[$aclRoleId]->isAllowed('category', 'read'));
    }

    /**
     * @param list<string> $privileges
     */
    private function createAclRole(array $privileges): string
    {
        $roleId = Uuid::randomHex();

        $this->connection->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($roleId),
            'name' => $roleId,
            'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $roleId;
    }
}
