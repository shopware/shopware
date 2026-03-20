<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1773829004RemoveLegacyProductStreamProductStatesFilter;

/**
 * @internal
 */
#[CoversClass(Migration1773829004RemoveLegacyProductStreamProductStatesFilter::class)]
class Migration1773829004RemoveLegacyProductStreamProductStatesFilterTest extends TestCase
{
    private Connection $connection;
    private string $streamId;
    private string $legacyFilterId;
    private string $newFilterId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->streamId = Uuid::randomBytes();
        $this->legacyFilterId = Uuid::randomBytes();
        $this->newFilterId = Uuid::randomBytes();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1773829004RemoveLegacyProductStreamProductStatesFilter();

        static::assertSame(1773829004, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('product_stream', [
            'id' => $this->streamId,
            'api_filter' => null,
            'invalid' => 0,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('product_stream_filter', [
            'id' => $this->legacyFilterId,
            'product_stream_id' => $this->streamId,
            'parent_id' => null,
            'type' => 'equalsAny',
            'field' => 'product.states',
            'operator' => null,
            'value' => 'is-legacy-only',
            'parameters' => null,
            'position' => 1,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('product_stream_filter', [
            'id' => $this->newFilterId,
            'product_stream_id' => $this->streamId,
            'parent_id' => null,
            'type' => 'equalsAny',
            'field' => 'type',
            'operator' => null,
            'value' => 'digital',
            'parameters' => null,
            'position' => 2,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $migration = new Migration1773829004RemoveLegacyProductStreamProductStatesFilter();

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(
            '0',
            (string) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `product_stream_filter` WHERE `field` IN (:fields)',
                ['fields' => ['states', 'product.states']],
                ['fields' => \Doctrine\DBAL\ArrayParameterType::STRING]
            )
        );

        static::assertSame(
            '1',
            (string) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `product_stream_filter` WHERE `id` = :id AND `field` = :field',
                ['id' => $this->newFilterId, 'field' => 'type']
            )
        );

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();
        static::assertArrayHasKey('product_stream.indexer', $indexers);
    }

    protected function tearDown(): void
    {
        $this->connection->delete('product_stream_filter', ['id' => $this->legacyFilterId]);
        $this->connection->delete('product_stream_filter', ['id' => $this->newFilterId]);
        $this->connection->delete('product_stream', ['id' => $this->streamId]);

        parent::tearDown();
    }
}
