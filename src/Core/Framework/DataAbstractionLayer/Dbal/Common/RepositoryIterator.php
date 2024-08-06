<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

/**
 * @template TEntityCollection of EntityCollection
 */
#[Package('core')]
class RepositoryIterator
{
    private readonly Criteria $criteria;

    /**
     * @var EntityRepository<TEntityCollection>
     */
    private readonly EntityRepository $repository;

    private readonly Context $context;

    private bool $autoIncrement = false;

    private bool $endReached = false;

    /**
     * @param EntityRepository<TEntityCollection> $repository
     */
    public function __construct(
        EntityRepository $repository,
        Context $context,
        ?Criteria $criteria = null
    ) {
        if ($criteria === null) {
            $criteria = new Criteria();
            $criteria->setOffset(0);
        }

        if ($criteria->getLimit() === null || $criteria->getLimit() < 1) {
            $criteria->setLimit(50);
        }

        if ($repository->getDefinition()->hasAutoIncrement()) {
            $criteria->addSorting(new FieldSorting('autoIncrement', FieldSorting::ASCENDING));
            $criteria->setFilter('increment', new RangeFilter('autoIncrement', [RangeFilter::GTE => 0]));
            $this->autoIncrement = true;
        }

        $this->criteria = $criteria;
        $this->repository = $repository;
        $this->context = clone $context;
    }

    public function reset(): void
    {
        if ($this->autoIncrement) {
            $filters = $this->criteria->getFilters();
            $this->criteria->resetFilters();
            unset($filters['increment']);

            foreach ($filters as $filterKey => $filter) {
                $this->criteria->setFilter($filterKey, $filter);
            }
        } else {
            $this->criteria->setOffset(0);
        }

        $this->endReached = false;
    }

    public function getTotal(): int
    {
        $criteria = clone $this->criteria;
        $criteria->setOffset(0);
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        return $this->repository->searchIds($criteria, $this->context)->getTotal();
    }

    /**
     * @return list<string>|list<array<string, string>>|null
     */
    public function fetchIds(): ?array
    {
        if ($this->endReached) {
            return null;
        }

        $this->criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $ids = $this->repository->searchIds($this->criteria, $this->context);

        $values = $ids->getIds();

        if (empty($values)) {
            $this->endReached = true;

            return null;
        }

        if (!$this->autoIncrement) {
            $this->criteria->setOffset($this->criteria->getOffset() + $this->criteria->getLimit());

            if (count($values) < $this->criteria->getLimit()) {
                $this->endReached = true;
            }

            return $values;
        }

        $last = end($values);
        if (!\is_string($last)) {
            throw new \RuntimeException('Expected string as last element of ids array');
        }

        $increment = $ids->getDataFieldOfId($last, 'autoIncrement') ?? 0;
        $this->criteria->setFilter('increment', new RangeFilter('autoIncrement', [RangeFilter::GT => $increment]));

        if (count($values) < $this->criteria->getLimit()) {
            $this->endReached = true;
        }

        return $values;
    }

    /**
     * @return EntitySearchResult<TEntityCollection>|null
     */
    public function fetch(): ?EntitySearchResult
    {
        if ($this->endReached) {
            return null;
        }

        $this->criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $result = $this->repository->search(clone $this->criteria, $this->context);

        // increase offset for next iteration
        $this->criteria->setOffset($this->criteria->getOffset() + $this->criteria->getLimit());

        if (empty($result->getIds())) {
            $this->endReached = true;

            return null;
        }

        if ($result->count() < $this->criteria->getLimit()) {
            $this->endReached = true;
        }

        return $result;
    }

    /**
     * @return iterable<Entity|PartialEntity>
     */
    public function iterateEntities(): iterable
    {
        try {
            while (($entityResult = $this->fetch()) instanceof EntitySearchResult) {
                // yield from is okay, as getElements keys by unique key
                yield from $entityResult->getElements();
            }
        } finally {
            $this->reset();
        }
    }

    /**
     * @return iterable<string>|iterable<array<string, string>>
     */
    public function iterateIds(): iterable
    {
        try {
            while (\is_array($ids = $this->fetchIds())) {
                // do not use yield from to ensure re-keying
                foreach ($ids as $id) {
                    yield $id;
                }
            }
        } finally {
            $this->reset();
        }
    }
}
