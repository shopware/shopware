<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_3\Migration1590408550AclResources;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1590408550AclResources
 */
class Migration1590408550AclResourcesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    /**
     * @after
     */
    public function restoreNewDatabase(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->rollBack();
        $connection->beginTransaction();
    }

    /**
     * @before
     */
    public function restoreOldDatabase(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $connection->rollBack();

        $connection->executeStatement('DELETE FROM acl_user_role');

        $connection->executeStatement('DROP TABLE IF EXISTS `acl_resource`');

        $connection->executeStatement('DELETE FROM acl_role');

        try {
            $connection->executeStatement('ALTER TABLE acl_role DROP COLUMN `privileges`');
        } catch (Exception) {
        }

        $sql = '
CREATE TABLE `acl_resource` (
  `resource` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `privilege` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `acl_role_id` binary(16) NOT NULL,
  `created_at` datetime(3) NOT NULL,
  `updated_at` datetime(3) DEFAULT NULL,
  PRIMARY KEY (`resource`,`privilege`,`acl_role_id`),
  KEY `fk.acl_resource.acl_role_id` (`acl_role_id`),
  CONSTRAINT `fk.acl_resource.acl_role_id` FOREIGN KEY (`acl_role_id`) REFERENCES `acl_role` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ';

        KernelLifecycleManager::getConnection()->executeStatement($sql);

        $connection->beginTransaction();
    }

    /**
     * @dataProvider migrationCases
     *
     * @param array<string, array<string>> $roles
     */
    public function testMigration(array $roles): void
    {
        $this->connection->rollBack();

        $this->insert($roles);

        $migration = new Migration1590408550AclResources();
        $migration->update($this->connection);

        $this->connection->beginTransaction();

        $actual = $this->fetchRoles();

        sort($actual);
        sort($roles);

        static::assertEquals($roles, $actual);
    }

    /**
     * @return array<string, array<string, array<string>>[]>
     */
    public function migrationCases(): array
    {
        return [
            'no roles or privs' => [
                [],
            ],
            'single role with multiple privs' => [
                ['admin' => ['product:read', 'product:write']],
            ],
            'single role without privs' => [
                ['admin' => []],
            ],
            'multiple roles with privs' => [
                [
                    'admin' => ['product:read', 'product:write'],
                    'editor' => ['media:read', 'media:write'],
                ],
            ],
            'multiple roles with and without privs' => [
                [
                    'admin' => ['product:read', 'product:write'],
                    'without' => [],
                ],
            ],
        ];
    }

    /**
     * @param array<string, array<string>> $roles
     */
    private function insert(array $roles): void
    {
        foreach ($roles as $name => $privileges) {
            $id = Uuid::randomBytes();

            $this->connection->insert('acl_role', [
                'id' => $id,
                'name' => $name,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            ]);

            foreach ($privileges as $privilege) {
                $priv = explode(':', $privilege);

                $this->connection->insert('acl_resource', [
                    'acl_role_id' => $id,
                    'resource' => $priv[0],
                    'privilege' => $priv[1],
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
                ]);
            }
        }
    }

    /**
     * @return array<string, array<string>>
     */
    private function fetchRoles(): array
    {
        /** @var array{name: string, priv: string}[] $roles */
        $roles = $this->connection->fetchAllAssociative('
            SELECT `role`.name,
            CONCAT(`resource`.`resource`, \':\', `resource`.`privilege`) as priv
            FROM acl_role `role`
                LEFT JOIN acl_resource `resource`
                    ON `role`.id = `resource`.acl_role_id
        ');

        $grouped = [];
        foreach ($roles as $role) {
            $name = $role['name'];

            if (!isset($grouped[$name])) {
                $grouped[$name] = [];
            }

            if (!$role['priv']) {
                continue;
            }
            $grouped[$name][] = $role['priv'];
        }

        return $grouped;
    }
}
