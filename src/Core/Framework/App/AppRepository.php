<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * Repository for app-domain code.
 *
 * Keep app lookups behind this repository.
 * Injecting the low-level DAL repository service (`app.repository`) should be a last resort for
 * cases that genuinely need low-level behavior this facade intentionally does not expose.
 *
 * @internal
 */
#[Package('framework')]
class AppRepository
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
}
