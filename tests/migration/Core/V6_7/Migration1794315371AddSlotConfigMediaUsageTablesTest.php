<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1794315371AddSlotConfigMediaUsageTables;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1794315371AddSlotConfigMediaUsageTables::class)]
class Migration1794315371AddSlotConfigMediaUsageTablesTest extends TestCase
{
    private const TABLES = [
        'cms_page_media_usage',
        'category_translation_media_usage',
        'product_translation_media_usage',
        'landing_page_translation_media_usage',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        foreach (self::TABLES as $table) {
            $this->connection->executeStatement('DROP TABLE IF EXISTS `' . $table . '`');
        }
    }

    protected function tearDown(): void
    {
        (new Migration1794315371AddSlotConfigMediaUsageTables())->update($this->connection);
        (new IndexerQueuer($this->connection))->finishIndexer(array_map(static fn (string $table) => $table . '.indexer', self::TABLES));
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1794315371, (new Migration1794315371AddSlotConfigMediaUsageTables())->getCreationTimestamp());
    }

    public function testCreatesTheTablesAndIsIdempotent(): void
    {
        $migration = new Migration1794315371AddSlotConfigMediaUsageTables();
        $migration->update($this->connection);
        $migration->update($this->connection);

        foreach (self::TABLES as $table) {
            static::assertTrue(TableHelper::tableExists($this->connection, $table), $table);
            static::assertTrue(TableHelper::foreignKeyExists($this->connection, $table, 'fk.' . $table . '.media_id'), $table);
        }

        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'cms_page_media_usage', 'fk.cms_page_media_usage.cms_page_id'));
        static::assertTrue(TableHelper::foreignKeyExists($this->connection, 'product_translation_media_usage', 'fk.product_translation_media_usage.translation'));
    }

    public function testRegistersTheIndexersFillingTheTables(): void
    {
        (new Migration1794315371AddSlotConfigMediaUsageTables())->update($this->connection);

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();

        foreach (self::TABLES as $table) {
            static::assertArrayHasKey($table . '.indexer', $indexers);
        }
    }
}
