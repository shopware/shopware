<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Common;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\RepositoryInterface;
use Shopware\Search\Criteria;

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
     * @var TranslationContext
     */
    private $context;

    public function __construct($repository, TranslationContext $context, Criteria $criteria = null)
    {
        if ($criteria === null) {
            $criteria = new Criteria();
            $criteria->setOffset(0);
            $criteria->setLimit(100);
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
        $criteria->setFetchCount(true);

        $result = $this->repository->searchUuids($criteria, $this->context);

        return $result->getTotal();
    }

    public function fetchUuids(): array
    {
        $this->criteria->setFetchCount(false);
        $uuids = $this->repository->searchUuids($this->criteria, $this->context);
        $this->criteria->setOffset($this->criteria->getOffset() + $this->criteria->getLimit());

        return $uuids->getUuids();
    }
}
