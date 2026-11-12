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
        $editorId = Uuid::randomBytes();
        $creatorId = Uuid::randomBytes();
        $deleterId = Uuid::randomBytes();
        $privilegedRoleId = Uuid::randomBytes();
        $unrelatedId = Uuid::randomBytes();
        $roles = [
            $roleId => ['experience_studio.viewer'],
            $editorId => ['experience_studio.viewer', 'experience_studio.editor'],
            $creatorId => ['experience_studio.viewer', 'experience_studio.editor', 'experience_studio.creator'],
            $deleterId => ['experience_studio.viewer', 'experience_studio.deleter'],
            $privilegedRoleId => ['experience_studio.viewer', 'user:read', 'media:read'],
            $unrelatedId => ['product.viewer'],
        ];
        foreach ($roles as $id => $privileges) {
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
            static::assertNotContains('user:read', $privileges);
            static::assertNotContains('media:read', $privileges);
            foreach ([
                $roleId => ['read'],
                $editorId => ['read', 'update'],
                $creatorId => ['read', 'update', 'create'],
                $deleterId => ['read', 'delete'],
            ] as $id => $operations) {
                $actual = json_decode($connection->fetchOne('SELECT privileges FROM acl_role WHERE id = ?', [$id]), true, 512, \JSON_THROW_ON_ERROR);
                static::assertIsArray($actual);
                foreach (['read', 'create', 'update', 'delete'] as $operation) {
                    static::assertCount(\in_array($operation, $operations, true) ? 1 : 0, array_keys($actual, 'content_workspace:' . $operation, true));
                }
            }
            $privilegedRole = json_decode($connection->fetchOne('SELECT privileges FROM acl_role WHERE id = ?', [$privilegedRoleId]), true, 512, \JSON_THROW_ON_ERROR);
            static::assertIsArray($privilegedRole);
            static::assertContains('user:read', $privilegedRole);
            static::assertContains('media:read', $privilegedRole);
            static::assertSame(['product.viewer'], json_decode($connection->fetchOne('SELECT privileges FROM acl_role WHERE id = ?', [$unrelatedId]), true, 512, \JSON_THROW_ON_ERROR));
        } finally {
            foreach (array_keys($roles) as $id) {
                $connection->delete('acl_role', ['id' => $id]);
            }
        }
    }
}
