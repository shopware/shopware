<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @template TEntityCollection of EntityCollection
 */
#[Package('framework')]
class SalesChannelRepositoryIterator
{
    private readonly Criteria $criteria;

    private bool $autoIncrement = false;

    /**
     * @param SalesChannelRepository<TEntityCollection> $repository
     */
    public function __construct(
        private readonly SalesChannelRepository $repository,
        private readonly SalesChannelContext $context,
        ?Criteria $criteria = null,
        private ?int $offset = null
    ) {
        if ($criteria === null) {
            $criteria = new Criteria();
            $criteria->setOffset(0);
            $criteria->setLimit(50);
        }

        if ($criteria->getSorting() === [] && $repository->getDefinition()->hasAutoIncrement()) {
            $criteria->addSorting(new FieldSorting('autoIncrement', FieldSorting::ASCENDING));
            $this->autoIncrement = true;
        } elseif ($this->offset !== null) {
            $criteria->setOffset($this->offset);
        }

        $this->criteria = $criteria;
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
     * @deprecated tag:v6.8.0 - Will be removed with the next major, as it is unused
     *
     * @return list<string>|list<array<string, string>>|null
     */
    public function fetchIds(): ?array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        $this->criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);
        $ids = $this->repository->searchIds($this->criteria, $this->context);
        $this->criteria->setOffset((int) $this->criteria->getOffset() + (int) $this->criteria->getLimit());

        if ($ids->getIds() !== []) {
            return $ids->getIds();
        }

        return null;
    }

    /**
     * @return EntitySearchResult<TEntityCollection>|null
     */
    public function fetch(): ?EntitySearchResult
    {
        if ($this->autoIncrement) {
            return $this->fetchByAutoIncrement();
        }

        $this->criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);
        $result = $this->repository->search($this->criteria, $this->context);

        $this->offset = (int) $this->criteria->getOffset() + (int) $this->criteria->getLimit();
        $this->criteria->setOffset($this->offset);

        if ($result->getEntities()->getIds() === []) {
            return null;
        }

        return $result;
    }

    public function getOffset(): int
    {
        return $this->offset ?? 0;
    }

    /**
     * @return EntitySearchResult<TEntityCollection>|null
     */
    private function fetchByAutoIncrement(): ?EntitySearchResult
    {
        $this->criteria->setOffset(0);
        $this->criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        if ($this->offset !== null) {
            $this->criteria->setFilter('increment', new RangeFilter('autoIncrement', [RangeFilter::GT => $this->offset]));
        }

        $result = $this->repository->search($this->criteria, $this->context);

        $last = $result->getEntities()->last();
        if ($last !== null) {
            $value = $last->get('autoIncrement');
            if (\is_int($value)) {
                $this->offset = $value;
            }
        }

        if ($result->getEntities()->getIds() === []) {
            return null;
        }

        return $result;
    }
}
