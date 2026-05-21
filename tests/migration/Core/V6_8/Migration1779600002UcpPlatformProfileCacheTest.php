<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_8\Migration1779600002UcpPlatformProfileCache;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600002UcpPlatformProfileCache::class)]
class Migration1779600002UcpPlatformProfileCacheTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600002, (new Migration1779600002UcpPlatformProfileCache())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_platform_profile_cache'));

        $migration = new Migration1779600002UcpPlatformProfileCache();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_platform_profile_cache'));

        foreach ([
            'id',
            'profile_uri',
            'profile_uri_hash',
            'profile_json',
            'etag',
            'fetched_at',
            'expires_at',
            'verification_status',
            'failure_count',
            'created_at',
            'updated_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_platform_profile_cache', $column),
                \sprintf('Column "%s" is missing from ucp_platform_profile_cache', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_platform_profile_cache', 'uniq.ucp_ppc.uri_hash'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_platform_profile_cache', 'idx.ucp_ppc.expires_at'));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'ucp_platform_profile_cache')) {
            $this->connection->executeStatement('DELETE FROM `ucp_platform_profile_cache`');
        }
        $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_platform_profile_cache`');
    }
}
