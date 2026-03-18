<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1773829001MigrateProductStreamProductStatesFilter;

/**
 * @internal
 */
#[CoversClass(Migration1773829001MigrateProductStreamProductStatesFilter::class)]
class Migration1773829001MigrateProductStreamProductStatesFilterTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigration(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $streamId = Uuid::randomBytes();
        $simpleFilterId = Uuid::randomBytes();
        $qualifiedFilterId = Uuid::randomBytes();
        $fallbackFilterId = Uuid::randomBytes();

        $this->connection->insert('product_stream', [
            'id' => $streamId,
            'api_filter' => null,
            'invalid' => 0,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('product_stream_filter', [
            'id' => $simpleFilterId,
            'product_stream_id' => $streamId,
            'parent_id' => null,
            'type' => 'equalsAny',
            'field' => 'states',
            'operator' => null,
            'value' => 'is-download|is-physical',
            'parameters' => null,
            'position' => 1,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('product_stream_filter', [
            'id' => $qualifiedFilterId,
            'product_stream_id' => $streamId,
            'parent_id' => null,
            'type' => 'equalsAny',
            'field' => 'product.states',
            'operator' => null,
            'value' => 'is-physical|is-legacy',
            'parameters' => null,
            'position' => 2,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->connection->insert('product_stream_filter', [
            'id' => $fallbackFilterId,
            'product_stream_id' => $streamId,
            'parent_id' => null,
            'type' => 'equalsAny',
            'field' => 'states',
            'operator' => null,
            'value' => 'is-legacy-only',
            'parameters' => null,
            'position' => 3,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $migration = new Migration1773829001MigrateProductStreamProductStatesFilter();

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

        $simpleFilter = $this->connection->fetchAssociative(
            'SELECT `field`, `value` FROM `product_stream_filter` WHERE `id` = :id',
            ['id' => $simpleFilterId]
        );

        static::assertIsArray($simpleFilter);
        static::assertSame('type', $simpleFilter['field']);
        static::assertSame('digital|physical', $simpleFilter['value']);

        $qualifiedFilter = $this->connection->fetchAssociative(
            'SELECT `field`, `value` FROM `product_stream_filter` WHERE `id` = :id',
            ['id' => $qualifiedFilterId]
        );

        static::assertIsArray($qualifiedFilter);
        static::assertSame('product.type', $qualifiedFilter['field']);
        static::assertSame('physical', $qualifiedFilter['value']);

        $fallbackFilter = $this->connection->fetchAssociative(
            'SELECT `field`, `value` FROM `product_stream_filter` WHERE `id` = :id',
            ['id' => $fallbackFilterId]
        );

        static::assertIsArray($fallbackFilter);
        static::assertSame('type', $fallbackFilter['field']);
        static::assertSame('physical', $fallbackFilter['value']);

        static::assertSame(
            '0',
            (string) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM `product_stream_filter` WHERE `product_stream_id` = :streamId AND `field` IN (:fields)',
                ['streamId' => $streamId, 'fields' => ['states', 'product.states']],
                ['fields' => ArrayParameterType::STRING]
            )
        );

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();
        static::assertArrayHasKey('product_stream.indexer', $indexers);
    }
}
