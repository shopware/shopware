<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class RepositoryIterator
{
    private readonly Criteria $criteria;

    private readonly EntityRepository $repository;

    private readonly Context $context;

    private bool $autoIncrement = false;

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

    public function getTotal(): int
    {
        $criteria = clone $this->criteria;
        $criteria->setOffset(0);
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->repository->searchIds($criteria, $this->context);

        return $result->getTotal();
    }

    public function fetchIds(): ?array
    {
        $this->criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $ids = $this->repository->searchIds($this->criteria, $this->context);

        $values = $ids->getIds();

        if (empty($values)) {
            return null;
        }

        if (!$this->autoIncrement) {
            $this->criteria->setOffset($this->criteria->getOffset() + $this->criteria->getLimit());

            return $values;
        }

        $last = end($values);
        if (!\is_string($last)) {
            throw new \RuntimeException('Expected string as last element of ids array');
        }

        $increment = $ids->getDataFieldOfId($last, 'autoIncrement');
        $this->criteria->setFilter('increment', new RangeFilter('autoIncrement', [RangeFilter::GT => $increment]));

        return $values;
    }

    public function fetch(): ?EntitySearchResult
    {
        $this->criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $result = $this->repository->search(clone $this->criteria, $this->context);

        // increase offset for next iteration
        $this->criteria->setOffset($this->criteria->getOffset() + $this->criteria->getLimit());

        if (empty($result->getIds())) {
            return null;
        }

        return $result;
    }
}
