<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\Discovery;

use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Collection\AgenticDiscoverySalesChannelConfigCollection;
use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Entity\AgenticDiscoverySalesChannelConfigEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves the agentic discovery configuration for a given Sales Channel id
 * with request-scoped memoisation. Keeps the rest of the discovery code free
 * of DAL boilerplate.
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @internal
 */
#[Package('framework')]
class AgenticDiscoveryConfigProvider
{
    /**
     * @var array<string, AgenticDiscoverySalesChannelConfigEntity|null>
     */
    private array $cache = [];

    /**
     * @param EntityRepository<AgenticDiscoverySalesChannelConfigCollection> $configRepository
     */
    public function __construct(
        private readonly EntityRepository $configRepository,
    ) {
    }

    public function forSalesChannel(string $salesChannelId, Context $context): ?AgenticDiscoverySalesChannelConfigEntity
    {
        if (\array_key_exists($salesChannelId, $this->cache)) {
            return $this->cache[$salesChannelId];
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannelId', $salesChannelId))
            ->setLimit(1);

        $entity = $this->configRepository->search($criteria, $context)->first();

        return $this->cache[$salesChannelId] = $entity instanceof AgenticDiscoverySalesChannelConfigEntity
            ? $entity
            : null;
    }

    public function clear(): void
    {
        $this->cache = [];
    }
}
