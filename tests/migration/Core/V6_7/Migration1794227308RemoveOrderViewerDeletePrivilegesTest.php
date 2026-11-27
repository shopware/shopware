<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1794227308RemoveOrderViewerDeletePrivileges;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1794227308RemoveOrderViewerDeletePrivileges::class)]
class Migration1794227308RemoveOrderViewerDeletePrivilegesTest extends TestCase
{
    use MigrationTestTrait;

    /**
     * @param list<string> $privileges
     * @param list<string> $expected
     */
    #[DataProvider('privilegeProvider')]
    public function testUpdatesExistingRoles(array $privileges, array $expected): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $id = Uuid::randomBytes();
        $connection->insert('acl_role', [
            'id' => $id,
            'name' => 'order viewer migration test',
            'privileges' => json_encode($privileges, \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $migration = new Migration1794227308RemoveOrderViewerDeletePrivileges();
        $migration->update($connection);
        $afterFirstRun = $connection->fetchAssociative('SELECT * FROM acl_role WHERE id = :id', ['id' => $id]);
        $migration->update($connection);
        $afterSecondRun = $connection->fetchAssociative('SELECT * FROM acl_role WHERE id = :id', ['id' => $id]);

        static::assertIsArray($afterFirstRun);
        static::assertSame($expected, json_decode((string) $afterFirstRun['privileges'], true, 512, \JSON_THROW_ON_ERROR));
        static::assertSame($afterFirstRun, $afterSecondRun);
        if ($privileges === $expected) {
            static::assertNull($afterFirstRun['updated_at']);
        } else {
            static::assertNotNull($afterFirstRun['updated_at']);
        }
    }

    /**
     * @return \Generator<string, array{list<string>, list<string>}>
     */
    public static function privilegeProvider(): \Generator
    {
        yield 'viewer loses order deletion and keeps shared privileges' => [
            ['order.viewer', 'order:read', 'order:delete', 'version:delete', 'customer:delete'],
            ['order.viewer', 'order:read', 'version:delete', 'customer:delete'],
        ];
        yield 'deleter keeps order deletion' => [
            ['order.viewer', 'order.deleter', 'order:delete', 'version:delete'],
            ['order.viewer', 'order.deleter', 'order:delete', 'version:delete'],
        ];
        yield 'editor keeps working version cleanup' => [
            ['order.viewer', 'order.editor', 'order:delete', 'version:delete'],
            ['order.viewer', 'order.editor', 'order:delete', 'version:delete'],
        ];
        yield 'custom raw privileges remain unchanged' => [
            ['order:read', 'order:delete', 'version:delete'],
            ['order:read', 'order:delete', 'version:delete'],
        ];
        yield 'already corrected viewer remains unchanged' => [
            ['order.viewer', 'order:read'],
            ['order.viewer', 'order:read'],
        ];
        yield 'empty role remains unchanged' => [[], []];
        yield 'extension version cleanup survives order viewer correction' => [
            ['order.viewer', 'quote.viewer', 'order:delete', 'version:delete'],
            ['order.viewer', 'quote.viewer', 'version:delete'],
        ];
        yield 'unknown extension permissions remain unchanged except order deletion' => [
            ['order.viewer', 'custom.editor', 'custom:delete', 'order:delete', 'version:delete'],
            ['order.viewer', 'custom.editor', 'custom:delete', 'version:delete'],
        ];
    }
}
