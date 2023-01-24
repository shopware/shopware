<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1673966228UpdateVersionAndOrderLineItemPrivilegeForOrderRoles;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1673966228UpdateVersionAndOrderLineItemPrivilegeForOrderRoles
 */
class Migration1673966228UpdateVersionAndOrderLineItemPrivilegeForOrderRolesTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testPrivilegesAreAddedToRole(): void
    {
        $roleId = Uuid::randomBytes();
        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test order viewer',
            'privileges' => \json_encode(['order.viewer']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1673966228UpdateVersionAndOrderLineItemPrivilegeForOrderRoles();
        $migration->update($this->connection);

        $privileges = $this->connection->fetchOne('
            SELECT privileges FROM acl_role WHERE id = :id
        ', [
            'id' => $roleId,
        ]);

        $privileges = \json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotFalse($privileges);
        static::assertContains('order:delete', $privileges);
        static::assertContains('version:delete', $privileges);

        $roleId = Uuid::randomBytes();
        $this->connection->insert('acl_role', [
            'id' => $roleId,
            'name' => 'test order editor',
            'privileges' => \json_encode(['order.editor']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1673966228UpdateVersionAndOrderLineItemPrivilegeForOrderRoles();
        $migration->update($this->connection);

        $privileges = $this->connection->fetchOne('
            SELECT privileges FROM acl_role WHERE id = :id
        ', [
            'id' => $roleId,
        ]);

        $privileges = \json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotFalse($privileges);
        static::assertContains('order_line_item:delete', $privileges);
    }

    public function testUnrelatedRolesAreNotUpdated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $privileges = ['property.editor'];
        $id = Uuid::randomBytes();
        $connection->insert('acl_role', [
            'id' => $id,
            'name' => 'test',
            'privileges' => \json_encode($privileges),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $before = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $id]);

        $migration = new Migration1673966228UpdateVersionAndOrderLineItemPrivilegeForOrderRoles();
        $migration->update($connection);

        $after = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $id]);

        static::assertSame($before, $after);
    }
}
