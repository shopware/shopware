<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class RepositoryIterator
{
    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var Context
     */
    private $context;

    public function __construct(EntityRepositoryInterface $repository, Context $context, ?Criteria $criteria = null)
    {
        if ($criteria === null) {
            $criteria = new Criteria();
            $criteria->setOffset(0);
        }

        if ($criteria->getLimit() === null || $criteria->getLimit() < 1) {
            $criteria->setLimit(50);
        }

        $this->criteria = $criteria;
        $this->repository = $repository;
        $this->context = $context;
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
        $this->criteria->setOffset($this->criteria->getOffset() + $this->criteria->getLimit());

        if (!empty($ids->getIds())) {
            return $ids->getIds();
        }

        return null;
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
