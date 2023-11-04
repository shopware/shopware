<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('core')]
class SalesChannelRepositoryIterator
{
    private readonly Criteria $criteria;

    public function __construct(
        private readonly SalesChannelRepository $repository,
        private readonly SalesChannelContext $context,
        ?Criteria $criteria = null
    ) {
        if ($criteria === null) {
            $criteria = new Criteria();
            $criteria->setOffset(0);
            $criteria->setLimit(50);
        }

        $this->criteria = $criteria;
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
        $result = $this->repository->search($this->criteria, $this->context);

        // increase offset for next iteration
        $this->criteria->setOffset($this->criteria->getOffset() + $this->criteria->getLimit());

        if (empty($result->getIds())) {
            return null;
        }

        return $result;
    }
}
