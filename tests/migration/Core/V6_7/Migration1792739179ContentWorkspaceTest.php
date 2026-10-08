<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1792739179ContentWorkspace;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1792739179ContentWorkspace::class)]
class Migration1792739179ContentWorkspaceTest extends TestCase
{
    public function testCreatesWorkspaceTableAndBackfillsOnlyStudioRolesIdempotently(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        if ($connection->createSchemaManager()->tablesExist(['content_workspace'])) {
            static::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM content_workspace'));
            $connection->executeStatement('DROP TABLE content_workspace');
        }
        $roleId = Uuid::randomBytes();
        $unrelatedId = Uuid::randomBytes();
        foreach ([$roleId => ['experience_studio.viewer'], $unrelatedId => ['product.viewer']] as $id => $privileges) {
            $connection->insert('acl_role', [
                'id' => $id, 'name' => 'Workspace migration test', 'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
                'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
        try {
            $migration = new Migration1792739179ContentWorkspace();
            $migration->update($connection);
            $migration->update($connection);
            static::assertSame(1792739179, $migration->getCreationTimestamp());
            static::assertTrue($connection->createSchemaManager()->tablesExist(['content_workspace']));
            $privileges = json_decode($connection->fetchOne('SELECT privileges FROM acl_role WHERE id = ?', [$roleId]), true, 512, \JSON_THROW_ON_ERROR);
            static::assertIsArray($privileges);
            foreach (['read', 'create', 'update', 'delete'] as $operation) {
                static::assertContains('content_workspace:' . $operation, $privileges);
                static::assertCount(1, array_keys($privileges, 'content_workspace:' . $operation, true));
            }
            static::assertSame(['product.viewer'], json_decode($connection->fetchOne('SELECT privileges FROM acl_role WHERE id = ?', [$unrelatedId]), true, 512, \JSON_THROW_ON_ERROR));
        } finally {
            $connection->delete('acl_role', ['id' => $roleId]);
            $connection->delete('acl_role', ['id' => $unrelatedId]);
        }
    }
}
