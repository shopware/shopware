<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\MyFakeNamespace;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class IndirectQueryInLoop
{
    private const HISTORY_TABLES = ['history' => 'product_history', 'lineItemHistory' => 'product_history'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<string> $ids
     *
     * @return list<array<string, mixed>>
     */
    public function loadPerId(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            $result[] = $this->loadOne($id);
        }

        return $result;
    }

    /**
     * The helper is two steps away from the loop, which still costs a query per iteration.
     *
     * @param list<string> $ids
     *
     * @return list<array<string, mixed>>
     */
    public function loadPerIdThroughTwoHelpers(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            $result[] = $this->delegate($id);
        }

        return $result;
    }

    /**
     * A helper that does not query may be called per iteration.
     *
     * @param list<string> $ids
     *
     * @return list<string>
     */
    public function formatPerId(array $ids): array
    {
        $result = [];

        foreach ($ids as $id) {
            $result[] = $this->format($id);
        }

        return $result;
    }

    /**
     * A chunked loop may call a querying helper: one query per chunk does not scale with the records.
     *
     * @param list<string> $ids
     */
    public function loadPerChunk(array $ids): void
    {
        foreach (array_chunk($ids, 250) as $chunk) {
            $this->loadMany($chunk);
        }
    }

    /**
     * One query per table of a class constant, with every id passed into it, is the batched form rather than an
     * N+1: the loop runs as often as the constant has entries.
     *
     * @param list<string> $ids
     */
    public function loadPerConstantTable(array $ids): void
    {
        foreach (array_unique(self::HISTORY_TABLES) as $table) {
            $this->loadManyFrom($table, $ids);
        }
    }

    /**
     * Mutual recursion walks a nested structure, so it is not one query per record even though the cycle queries.
     *
     * @param list<array<string, mixed>> $nodes
     */
    public function walkTree(array $nodes): void
    {
        foreach ($nodes as $node) {
            $this->descend($node);
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    private function descend(array $node): void
    {
        $this->loadOne((string) $node['id']);

        foreach ($node['children'] ?? [] as $child) {
            $this->walkTree([$child]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadOne(string $id): array
    {
        $row = $this->connection->fetchAssociative('SELECT id FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);

        return $row === false ? [] : $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function delegate(string $id): array
    {
        return $this->loadOne($id);
    }

    private function format(string $id): string
    {
        return strtoupper($id);
    }

    /**
     * @param list<string> $ids
     *
     * @return list<array<string, mixed>>
     */
    private function loadMany(array $ids): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id FROM product WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @param list<string> $ids
     *
     * @return list<array<string, mixed>>
     */
    private function loadManyFrom(string $table, array $ids): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT id FROM `' . $table . '` WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
