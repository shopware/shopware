<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1627650101AddUploadPluginRolePrivilege;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1627650101AddUploadPluginRolePrivilege
 */
class Migration1627650101AddUploadPluginRolePrivilegeTest extends TestCase
{
    use MigrationTestTrait;

    public function testNewPermissionsAreAdded(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $id = Uuid::randomBytes();
        $connection->insert('acl_role', [
            'id' => $id,
            'name' => 'test',
            'privileges' => \json_encode(['system.plugin_maintain']),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1627650101AddUploadPluginRolePrivilege();
        $migration->update($connection);

        $privileges = $connection->fetchOne('SELECT privileges FROM acl_role WHERE id = :id', ['id' => $id]);
        $privileges = \json_decode((string) $privileges, true, 512, \JSON_THROW_ON_ERROR);

        static::assertContains('user_config:read', $privileges);
        static::assertContains('user_config:create', $privileges);
        static::assertContains('user_config:update', $privileges);
        static::assertContains('system.plugin_upload', $privileges);
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

        $migration = new Migration1627650101AddUploadPluginRolePrivilege();
        $migration->update($connection);

        $after = $connection->fetchAssociative('SELECT * FROM `acl_role` WHERE id = :id', ['id' => $id]);

        static::assertSame($before, $after);
    }
}
