<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1590408550AclResources;

class Migration1590408550AclResourcesTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @after
     */
    public function restoreNewDatabase(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->rollBack();
        $connection->beginTransaction();
    }

    /**
     * @before
     */
    public function restoreOldDatabase(): void
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->rollBack();

        $connection->executeUpdate('DELETE FROM acl_user_role');

        $connection->executeUpdate('DROP TABLE IF EXISTS `acl_resource`');

        $connection->executeUpdate('DELETE FROM acl_role');

        try {
            $connection->executeUpdate('ALTER TABLE acl_role DROP COLUMN `privileges`');
        } catch (DBALException $e) {
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

        $this->getContainer()
            ->get(Connection::class)
            ->executeUpdate($sql);

        $connection->beginTransaction();
    }

    /**
     * @dataProvider migrationCases
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

    public function migrationCases()
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

    private function fetchRoles()
    {
        $roles = $this->connection->fetchAll("
            SELECT `role`.name,
            CONCAT(`resource`.`resource`, ':', `resource`.`privilege`) as priv
            FROM acl_role `role`
                LEFT JOIN acl_resource `resource`
                    ON `role`.id = `resource`.acl_role_id
        ");

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
