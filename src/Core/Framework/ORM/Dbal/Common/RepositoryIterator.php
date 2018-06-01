<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Dbal\Common;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\SearchResultInterface;

class RepositoryIterator
{
    /**
     * @var Criteria
     */
    private $criteria;

    /**
     * @var RepositoryInterface
     */
    private $repository;

    /**
     * @var \Shopware\Core\Framework\Context
     */
    private $context;

    public function __construct(RepositoryInterface $repository, Context $context, Criteria $criteria = null)
    {
        if ($criteria === null) {
            $criteria = new Criteria();
            $criteria->setOffset(0);
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
        $criteria->setFetchCount(Criteria::FETCH_COUNT_TOTAL);

        $result = $this->repository->searchIds($criteria, $this->context);

        return $result->getTotal();
    }

    public function fetchIds(): ?array
    {
        $this->criteria->setFetchCount(Criteria::FETCH_COUNT_NONE);
        $ids = $this->repository->searchIds($this->criteria, $this->context);
        $this->criteria->setOffset($this->criteria->getOffset() + $this->criteria->getLimit());

        if (!empty($ids->getIds())) {
            return $ids->getIds();
        }

        return null;
    }

    public function fetch(): ?SearchResultInterface
    {
        $this->criteria->setFetchCount(Criteria::FETCH_COUNT_NONE);
        $result = $this->repository->search($this->criteria, $this->context);
        $this->criteria->setOffset($this->criteria->getOffset() + $this->criteria->getLimit());

        if (empty($result->getIdResult()->getIds())) {
            return null;
        }

        return $result;
    }
}
