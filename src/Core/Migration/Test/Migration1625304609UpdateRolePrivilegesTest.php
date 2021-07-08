<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1625304609UpdateRolePrivileges;

class Migration1625304609UpdateRolePrivilegesTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testNewPermissionsAreAdded(): void
    {
        $aclRoleRepo = $this->getContainer()->get('acl_role.repository');
        $connection = $this->getContainer()->get(Connection::class);
        $context = Context::createDefaultContext();

        $userId = Uuid::randomHex();
        $this->createUser($userId);

        $aclRoleId = Uuid::randomHex();
        $this->createAclRole($aclRoleId);

        $this->createAclUserRole($userId, $aclRoleId);

        $migration = new Migration1625304609UpdateRolePrivileges();
        $migration->update($connection);

        $apps = $this->getAllApps($connection);
        $appPrivileges = $this->getAppPrivileges($apps);

        /** @var AclRoleEntity $role */
        $role = $aclRoleRepo->search(new Criteria([$aclRoleId]), $context)->first();
        static::assertNotNull($role);

        foreach ($appPrivileges as $appPrivilege) {
            static::assertContains($appPrivilege, $role->getPrivileges());
        }
    }

    private function createUser($userId): void
    {
        $this->getContainer()->get(Connection::class)->insert('user', [
            'id' => Uuid::fromHexToBytes($userId),
            'first_name' => 'test',
            'last_name' => '',
            'email' => 'test@example.com',
            'username' => 'userTest',
            'password' => password_hash('123456', \PASSWORD_BCRYPT),
            'locale_id' => Uuid::fromHexToBytes($this->getLocaleIdOfSystemLanguage()),
            'active' => 1,
            'admin' => 1,
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAclRole($aclRoleId): void
    {
        $this->getContainer()->get(Connection::class)->insert('acl_role', [
            'id' => Uuid::fromHexToBytes($aclRoleId),
            'name' => 'aclTest',
            'privileges' => json_encode(['users_and_permissions.viewer']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createAclUserRole($userId, $aclRoleId): void
    {
        $this->getContainer()->get(Connection::class)->insert('acl_user_role', [
            'user_id' => Uuid::fromHexToBytes($userId),
            'acl_role_id' => Uuid::fromHexToBytes($aclRoleId),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function getAllApps(Connection $connection): array
    {
        return $connection->executeQuery('SELECT name FROM `app`')->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function getAppPrivileges(array $appNames): array
    {
        $privileges = [
            'app.all',
        ];

        foreach ($appNames as $appName) {
            $privileges = array_merge($privileges, [
                'app.' . $appName,
            ]);
        }

        return $privileges;
    }
}
