<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1625304609UpdateRolePrivileges;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1625304609UpdateRolePrivileges
 */
class Migration1625304609UpdateRolePrivilegesTest extends TestCase
{
    use MigrationTestTrait;

    public function testNewPermissionsAreAdded(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $userId = Uuid::randomBytes();
        $this->createUser($userId);

        $aclRoleId = Uuid::randomBytes();
        $this->createAclRole($aclRoleId);

        $this->createAclUserRole($userId, $aclRoleId);

        $migration = new Migration1625304609UpdateRolePrivileges();
        $migration->update($connection);

        $apps = $this->getAllApps($connection);
        $appPrivileges = $this->getAppPrivileges($apps);

        $privileges = $connection->fetchOne('SELECT privileges FROM acl_role WHERE id = :id', ['id' => $aclRoleId]);
        $privileges = \json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);

        foreach ($appPrivileges as $appPrivilege) {
            static::assertContains($appPrivilege, $privileges);
        }
    }

    public function testCanHandleAclRoleWithObjectifiedPrivileges(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $userId = Uuid::randomBytes();
        $this->createUser($userId);

        $aclRoleId = Uuid::randomBytes();
        $this->createAclRole($aclRoleId);

        $connection->executeStatement('
            UPDATE `acl_role`
            SET `privileges` = \'{"0": "users_and_permissions.viewer"}\'
            WHERE id = :id
        ', ['id' => $aclRoleId]);

        $this->createAclUserRole($userId, $aclRoleId);

        $migration = new Migration1625304609UpdateRolePrivileges();
        $migration->update($connection);

        $apps = $this->getAllApps($connection);
        $appPrivileges = $this->getAppPrivileges($apps);

        $privileges = $connection->fetchOne('SELECT privileges FROM acl_role WHERE id = :id', ['id' => $aclRoleId]);
        $privileges = \json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);

        foreach ($appPrivileges as $appPrivilege) {
            static::assertContains($appPrivilege, $privileges);
        }
    }

    private function createUser(string $userId): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->insert('user', [
            'id' => $userId,
            'first_name' => 'test',
            'last_name' => '',
            'email' => 'test@example.com',
            'username' => 'userTest',
            'password' => password_hash('123456', \PASSWORD_BCRYPT),
            'locale_id' => $connection->fetchOne('SELECT id FROM locale WHERE code = "en-GB"'),
            'active' => 1,
            'admin' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAclRole(string $aclRoleId): void
    {
        KernelLifecycleManager::getConnection()->insert('acl_role', [
            'id' => $aclRoleId,
            'name' => 'aclTest',
            'privileges' => json_encode(['users_and_permissions.viewer']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAclUserRole(string $userId, string $aclRoleId): void
    {
        KernelLifecycleManager::getConnection()->insert('acl_user_role', [
            'user_id' => $userId,
            'acl_role_id' => $aclRoleId,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    /**
     * @return array<string>
     */
    private function getAllApps(Connection $connection): array
    {
        return $connection->executeQuery('SELECT name FROM `app`')->fetchFirstColumn();
    }

    /**
     * @param array<string> $appNames
     *
     * @return array<string>
     */
    private function getAppPrivileges(array $appNames): array
    {
        $privileges = [
            'app.all',
        ];

        foreach ($appNames as $appName) {
            $privileges = [...$privileges, ...[
                'app.' . $appName,
            ]];
        }

        return $privileges;
    }
}
