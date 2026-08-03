<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\MyFakeNamespace;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ValidQueryUsage
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $parentIds
     *
     * @return array<string, array<string, string>>
     */
    public function batchedFetch(array $parentIds): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(parent_id)) AS parentId, id, cheapest_price_accessor AS accessor
             FROM product WHERE parent_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($parentIds)],
            ['ids' => ArrayParameterType::BINARY]
        );

        $accessors = [];
        foreach ($rows as $row) {
            $accessors[$row['parentId']][$row['id']] = $row['accessor'];
        }

        return $accessors;
    }

    /**
     * @param list<string> $ids
     */
    public function chunkedFetch(array $ids): void
    {
        foreach (array_chunk($ids, 250) as $chunk) {
            $this->connection->fetchAllAssociative(
                'SELECT id FROM product WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($chunk)],
                ['ids' => ArrayParameterType::BINARY]
            );
        }
    }

    public function paginatedFetch(IterableQuery $iterator): void
    {
        while ($ids = $iterator->fetch()) {
            $this->connection->fetchAllAssociative(
                'SELECT id FROM product WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList(array_values($ids))],
                ['ids' => ArrayParameterType::BINARY]
            );
        }
    }

    public function fetchPerFixedTable(): void
    {
        foreach (['product', 'category'] as $table) {
            $this->connection->fetchOne('SELECT COUNT(*) FROM `' . $table . '`');
        }
    }

    /**
     * @param list<string> $ids
     */
    public function writePerId(array $ids): void
    {
        $statement = $this->connection->prepare('UPDATE product SET active = 1 WHERE id = :id');

        foreach ($ids as $id) {
            $statement->executeStatement(['id' => Uuid::fromHexToBytes($id)]);
        }
    }

    /**
     * The chunking happened on an earlier line, so every iteration handles a whole batch of ids.
     *
     * @param list<string> $ids
     */
    public function fetchPerPrebuiltChunk(array $ids): void
    {
        $chunks = array_chunk($ids, 250);

        foreach ($chunks as $chunk) {
            $this->connection->fetchAllAssociative(
                'SELECT id FROM product WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($chunk)],
                ['ids' => ArrayParameterType::BINARY]
            );
        }
    }

    /**
     * @param list<array{languageId: string, ids: list<string>}> $groups
     */
    public function fetchPerDestructuredBatch(array $groups): void
    {
        foreach ($groups as ['languageId' => $languageId, 'ids' => $groupIds]) {
            $this->connection->fetchAllAssociative(
                'SELECT id FROM product_translation WHERE product_id IN (:ids) AND language_id = :languageId',
                ['ids' => Uuid::fromHexToBytesList($groupIds), 'languageId' => Uuid::fromHexToBytes($languageId)],
                ['ids' => ArrayParameterType::BINARY]
            );
        }
    }

    /**
     * A `LIMIT`-ed query drained until it runs dry: every iteration handles a page, not a record.
     */
    public function drainPaginatedQuery(): void
    {
        do {
            $ids = $this->connection->fetchFirstColumn('SELECT id FROM product WHERE stock != available_stock LIMIT 500');

            if ($ids === []) {
                break;
            }

            $this->connection->executeStatement(
                'UPDATE product SET available_stock = stock WHERE id IN (:ids)',
                ['ids' => $ids],
                ['ids' => ArrayParameterType::BINARY]
            );
        } while ($ids !== []);
    }

    /**
     * A worklist consumed until empty, with each iteration querying the whole pending set.
     */
    public function drainWorklist(string $parentId): void
    {
        $pendingIds = [$parentId];

        while ($pendingIds !== []) {
            $pendingIds = $this->connection->fetchFirstColumn(
                'SELECT LOWER(HEX(id)) FROM category WHERE parent_id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($pendingIds)],
                ['ids' => ArrayParameterType::BINARY]
            );
        }
    }
}
