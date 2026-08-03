<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\MyFakeNamespace;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class QueryInLoop
{
    /**
     * @param EntityRepository<EntityCollection<Entity>> $repository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $repository
    ) {
    }

    /**
     * @param list<string> $parentIds
     *
     * @return list<array<string, mixed>>
     */
    public function fetchPerParent(array $parentIds): array
    {
        $result = [];

        foreach ($parentIds as $parentId) {
            $result[] = $this->connection->fetchAllKeyValue(
                'SELECT id, cheapest_price_accessor FROM product WHERE parent_id = :id',
                ['id' => Uuid::fromHexToBytes($parentId)]
            );
        }

        return $result;
    }

    /**
     * @param list<string> $parentIds
     *
     * @return list<array<string, mixed>>
     */
    public function fetchPerParentWithQueryBuilder(array $parentIds): array
    {
        $result = [];

        foreach ($parentIds as $parentId) {
            $query = $this->connection->createQueryBuilder();
            $query->select('id');
            $query->from('product');
            $query->where('parent_id = :id');
            $query->setParameter('id', Uuid::fromHexToBytes($parentId));

            $result[] = $query->fetchAllAssociative();
        }

        return $result;
    }

    /**
     * @param list<string> $ids
     */
    public function searchPerId(array $ids, Context $context): void
    {
        foreach ($ids as $id) {
            $this->repository->search(new Criteria([$id]), $context);
        }
    }

    /**
     * @param list<string> $ids
     */
    public function updatePerId(array $ids, Context $context): void
    {
        foreach ($ids as $id) {
            $this->repository->update([['id' => $id, 'active' => true]], $context);
        }
    }

    /**
     * @param list<string> $ids
     */
    public function nestedLoop(array $ids, Context $context): void
    {
        foreach ($ids as $id) {
            foreach (['de-DE', 'en-GB'] as $locale) {
                $criteria = new Criteria([$id]);
                $criteria->addFilter(new EqualsFilter('locale', $locale));

                $this->repository->searchIds($criteria, $context);
            }
        }
    }

    /**
     * @param list<string> $ids
     */
    public function whileLoop(array $ids): void
    {
        $offset = 0;

        while ($offset < \count($ids)) {
            $this->connection->fetchOne('SELECT id FROM product WHERE id = :id', ['id' => $ids[$offset]]);
            ++$offset;
        }
    }

    /**
     * @param list<string> $ids
     */
    public function forLoop(array $ids): void
    {
        for ($i = 0; $i < \count($ids); ++$i) {
            $this->connection->fetchOne('SELECT id FROM product WHERE id = :id', ['id' => $ids[$i]]);
        }
    }

    /**
     * A sibling method that paginates must not make the `while` loops of this class look like drain loops.
     */
    public function unrelatedPaginatedMethod(): void
    {
        $this->connection->fetchFirstColumn('SELECT id FROM product LIMIT 500');
    }

    /**
     * A record represented as a map is a single record, not a batch of values.
     *
     * @param list<array{id: string}> $rows
     */
    public function rowByRow(array $rows): void
    {
        foreach ($rows as $row) {
            $this->connection->fetchOne('SELECT id FROM product WHERE id = :id', ['id' => $row['id']]);
        }
    }

    /**
     * A row-by-row result iteration is not a chunked pagination loop, so the query in the body still runs once per
     * record.
     */
    public function resultIteration(Result $result): void
    {
        while ($row = $result->fetchAssociative()) {
            $this->connection->fetchOne('SELECT id FROM product WHERE id = :id', ['id' => $row['id']]);
        }
    }
}
