<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * Storage facade for app-domain code.
 *
 * Keep app lookups behind this storage.
 * Injecting the low-level DAL repository service (`app.repository`) should be a last resort for
 * cases that genuinely need low-level behavior this facade intentionally does not expose.
 *
 * @internal
 */
#[Package('framework')]
class AppStorage
{
    /**
     * @param EntityRepository<AppCollection> $repository
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function findById(string $id, Context $context): ?AppEntity
    {
        $criteria = new Criteria([$id]);
        $criteria->addFilter(new EqualsFilter('selfManaged', false));

        return $this->repository->search($criteria, $context)->getEntities()->first();
    }

    public function findByName(string $name, Context $context): ?AppEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', false));
        $criteria->addFilter(new EqualsFilter('name', $name));

        return $this->repository->search($criteria, $context)->getEntities()->first();
    }

    public function findAll(Context $context): AppCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', false));

        return $this->repository->search($criteria, $context)->getEntities();
    }

    public function findAllWithNameOrLabel(string $filter, Context $context): AppCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('selfManaged', false));
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new ContainsFilter('name', $filter),
                new ContainsFilter('label', $filter),
            ]
        ));

        return $this->repository->search($criteria, $context)->getEntities();
    }
}
