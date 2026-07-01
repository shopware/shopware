<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\File\Loader;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;

/**
 * @internal
 */
#[Package('framework')]
class SalesChannelFileConfigurationLoader
{
    /**
     * @param EntityRepository<SalesChannelFileCollection> $repository
     */
    public function __construct(private readonly EntityRepository $repository)
    {
    }

    public function load(string $fileFamily, string $fileName, string $salesChannelId, Context $context): ?SalesChannelFileEntity
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->addFilter(new EqualsFilter('fileFamily', $fileFamily))
            ->addFilter(new EqualsFilter('fileName', $fileName))
            ->setLimit(1);

        return $this->repository->search($criteria, $context)->first();
    }

    /**
     * @return array<string, SalesChannelFileEntity>
     */
    public function loadForFileFamily(string $fileFamily, string $salesChannelId, Context $context): array
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->addFilter(new EqualsFilter('fileFamily', $fileFamily));

        $entities = $this->repository->search($criteria, $context);
        $configurations = [];

        foreach ($entities as $entity) {
            $configurations[$entity->getFileName()] = $entity;
        }

        return $configurations;
    }
}
