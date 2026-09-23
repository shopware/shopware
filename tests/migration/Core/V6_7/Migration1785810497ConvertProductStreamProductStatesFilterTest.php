<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\IndexerQueuer;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1785810497ConvertProductStreamProductStatesFilter;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1785810497ConvertProductStreamProductStatesFilter::class)]
class Migration1785810497ConvertProductStreamProductStatesFilterTest extends TestCase
{
    private Connection $connection;

    private string $streamId;

    private string $simpleFilterId;

    private string $qualifiedFilterId;

    private string $partiallyUnknownFilterId;

    private string $unknownFilterId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
        $this->streamId = Uuid::randomBytes();
        $this->simpleFilterId = Uuid::randomBytes();
        $this->qualifiedFilterId = Uuid::randomBytes();
        $this->partiallyUnknownFilterId = Uuid::randomBytes();
        $this->unknownFilterId = Uuid::randomBytes();
    }

    protected function tearDown(): void
    {
        $this->connection->delete('product_stream_filter', ['id' => $this->simpleFilterId]);
        $this->connection->delete('product_stream_filter', ['id' => $this->qualifiedFilterId]);
        $this->connection->delete('product_stream_filter', ['id' => $this->partiallyUnknownFilterId]);
        $this->connection->delete('product_stream_filter', ['id' => $this->unknownFilterId]);
        $this->connection->delete('product_stream', ['id' => $this->streamId]);

        parent::tearDown();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1785810497ConvertProductStreamProductStatesFilter();

        static::assertSame(1785810497, $migration->getCreationTimestamp());
    }

    public function testUpdateConvertsLegacyFilters(): void
    {
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->insert('product_stream', [
            'id' => $this->streamId,
            'api_filter' => null,
            'invalid' => 0,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);

        $this->insertFilter($this->simpleFilterId, 1, 'states', 'is-download|is-physical', $createdAt);
        $this->insertFilter($this->qualifiedFilterId, 2, 'product.states', 'is-download', $createdAt);
        $this->insertFilter($this->partiallyUnknownFilterId, 3, 'product.states', 'is-physical|is-legacy', $createdAt);
        $this->insertFilter($this->unknownFilterId, 4, 'states', 'is-legacy-only', $createdAt);

        $migration = new Migration1785810497ConvertProductStreamProductStatesFilter();

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertSame(['type', 'digital|physical'], $this->fetchFilter($this->simpleFilterId));
        static::assertSame(['product.type', 'digital'], $this->fetchFilter($this->qualifiedFilterId));

        // filters mentioning a state without a product type counterpart are left untouched for manual review
        static::assertSame(['product.states', 'is-physical|is-legacy'], $this->fetchFilter($this->partiallyUnknownFilterId));
        static::assertSame(['states', 'is-legacy-only'], $this->fetchFilter($this->unknownFilterId));

        $indexers = (new IndexerQueuer($this->connection))->getIndexers();
        static::assertArrayHasKey('product_stream.indexer', $indexers);
    }

    private function insertFilter(string $id, int $position, string $field, string $value, string $createdAt): void
    {
        $this->connection->insert('product_stream_filter', [
            'id' => $id,
            'product_stream_id' => $this->streamId,
            'parent_id' => null,
            'type' => 'equalsAny',
            'field' => $field,
            'operator' => null,
            'value' => $value,
            'parameters' => null,
            'position' => $position,
            'custom_fields' => null,
            'created_at' => $createdAt,
            'updated_at' => null,
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function fetchFilter(string $id): array
    {
        $filter = $this->connection->fetchAssociative(
            'SELECT `field`, `value` FROM `product_stream_filter` WHERE `id` = :id',
            ['id' => $id]
        );

        static::assertIsArray($filter);
        static::assertIsString($filter['field']);
        static::assertIsString($filter['value']);

        return [$filter['field'], $filter['value']];
    }
}
