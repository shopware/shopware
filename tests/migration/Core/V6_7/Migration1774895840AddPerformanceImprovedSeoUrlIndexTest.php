<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1774895840AddPerformanceImprovedSeoUrlIndex;

/**
 * @internal
 */
#[CoversClass(Migration1774895840AddPerformanceImprovedSeoUrlIndex::class)]
class Migration1774895840AddPerformanceImprovedSeoUrlIndexTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testIndexIsCreated(): void
    {
        if (TableHelper::indexExists($this->connection, 'seo_url', 'idx.path_info')) {
            $this->connection->executeStatement('DROP INDEX `idx.path_info` ON `seo_url`');
        }

        $migration = new Migration1774895840AddPerformanceImprovedSeoUrlIndex();
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexExists($this->connection, 'seo_url', 'idx.path_info'));
        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'seo_url', 'idx.path_info', ['path_info', 'is_canonical', 'sales_channel_id', 'language_id', 'seo_path_info']));
    }

    public function testMigrationIsIdempotent(): void
    {
        $migration = new Migration1774895840AddPerformanceImprovedSeoUrlIndex();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::indexSpansColumns($this->connection, 'seo_url', 'idx.path_info', ['path_info', 'is_canonical', 'sales_channel_id', 'language_id', 'seo_path_info']));
    }
}
