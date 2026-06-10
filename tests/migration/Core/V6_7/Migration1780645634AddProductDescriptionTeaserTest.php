<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1780645634AddProductDescriptionTeaser;

/**
 * @internal
 */
#[CoversClass(Migration1780645634AddProductDescriptionTeaser::class)]
class Migration1780645634AddProductDescriptionTeaserTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1780645634, (new Migration1780645634AddProductDescriptionTeaser())->getCreationTimestamp());
    }

    public function testUpdateAddsPlainColumn(): void
    {
        $this->dropTeaserColumnIfExists();

        $migration = new Migration1780645634AddProductDescriptionTeaser();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = $this->connection->fetchAssociative(
            'SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = \'product_translation\' AND COLUMN_NAME = \'description_teaser\''
        );

        static::assertIsArray($column);
        static::assertSame('varchar', $column['DATA_TYPE']);
        static::assertSame(512, (int) $column['CHARACTER_MAXIMUM_LENGTH']);
        static::assertStringNotContainsStringIgnoringCase('generated', (string) $column['EXTRA']);
    }

    public function testUpdateRegistersBackfillIndexer(): void
    {
        $this->dropTeaserColumnIfExists();

        try {
            $migration = new Migration1780645634AddProductDescriptionTeaser();
            $migration->update($this->connection);
            $migration->update($this->connection);

            $indexers = (new IndexerQueuer($this->connection))->getIndexers();

            static::assertArrayHasKey('product.description_teaser.indexer', $indexers);
        } finally {
            (new IndexerQueuer($this->connection))->finishIndexer(['product.description_teaser.indexer']);
        }
    }

    private function dropTeaserColumnIfExists(): void
    {
        if (TableHelper::columnExists($this->connection, 'product_translation', 'description_teaser')) {
            $this->connection->executeStatement('ALTER TABLE `product_translation` DROP COLUMN `description_teaser`');
        }
    }
}
