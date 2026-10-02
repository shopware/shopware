<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1790338905AddConfigHashToRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(Migration1790338905AddConfigHashToRule::class)]
class Migration1790338905AddConfigHashToRuleTest extends TestCase
{
    private Connection $connection;

    private IndexerQueuer $indexerQueuer;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->indexerQueuer = new IndexerQueuer($this->connection);
        $this->indexerQueuer->finishIndexer(['rule.indexer']);
    }

    protected function tearDown(): void
    {
        $this->indexerQueuer->finishIndexer(['rule.indexer']);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1790338905, (new Migration1790338905AddConfigHashToRule())->getCreationTimestamp());
    }

    public function testAddsNullableColumnWithIndexAndQueuesTheRuleIndexer(): void
    {
        $this->rollback();

        $migration = new Migration1790338905AddConfigHashToRule();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $table = $this->connection->createSchemaManager()->introspectTableByUnquotedName('rule');
        static::assertFalse($table->getColumn('config_hash')->getNotnull());
        static::assertTrue(TableHelper::indexExists($this->connection, 'rule', 'idx.rule.config_hash'));

        static::assertSame([], $this->indexerQueuer->getIndexers()['rule.indexer'] ?? null);
    }

    public function testKeepsAFullRuleIndexQueuedByAnotherMigration(): void
    {
        $this->rollback();
        IndexerQueuer::registerIndexer($this->connection, 'rule.indexer');

        (new Migration1790338905AddConfigHashToRule())->update($this->connection);

        static::assertSame([], $this->indexerQueuer->getIndexers()['rule.indexer'] ?? null);
    }

    public function testDoesNotQueueTheIndexerAgainWhenTheColumnExists(): void
    {
        $migration = new Migration1790338905AddConfigHashToRule();
        $migration->update($this->connection);
        $this->indexerQueuer->finishIndexer(['rule.indexer']);

        $migration->update($this->connection);

        static::assertArrayNotHasKey('rule.indexer', $this->indexerQueuer->getIndexers());
    }

    private function rollback(): void
    {
        if (TableHelper::indexExists($this->connection, 'rule', 'idx.rule.config_hash')) {
            $this->connection->executeStatement('DROP INDEX `idx.rule.config_hash` ON `rule`');
        }

        if (TableHelper::columnExists($this->connection, 'rule', 'config_hash')) {
            $this->connection->executeStatement('ALTER TABLE `rule` DROP COLUMN `config_hash`');
        }
    }
}
