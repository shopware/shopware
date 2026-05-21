<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Discovery;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Collection\UcpSalesChannelConfigCollection;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSalesChannelConfigEntity;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Resolves the UCP configuration for a given Sales Channel id with
 * request-scoped memoisation. Keeps the rest of the UCP code free of
 * DAL boilerplate.
 *
 * @internal
 */
#[Package('framework')]
class UcpConfigProvider
{
    /**
     * @var array<string, UcpSalesChannelConfigEntity|null>
     */
    private array $cache = [];

    /**
     * @param EntityRepository<UcpSalesChannelConfigCollection> $configRepository
     */
    public function __construct(
        private readonly EntityRepository $configRepository,
    ) {
    }

    public function forSalesChannel(string $salesChannelId, Context $context): ?UcpSalesChannelConfigEntity
    {
        if (\array_key_exists($salesChannelId, $this->cache)) {
            return $this->cache[$salesChannelId];
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->setLimit(1);

        $entity = $this->configRepository->search($criteria, $context)->first();

        return $this->cache[$salesChannelId] = $entity instanceof UcpSalesChannelConfigEntity ? $entity : null;
    }

    public function clear(): void
    {
        $this->cache = [];
    }
}
