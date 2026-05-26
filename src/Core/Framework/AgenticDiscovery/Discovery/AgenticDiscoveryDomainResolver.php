<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\Discovery;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * Maps an incoming public HTTP request to the storefront sales-channel domain
 * it belongs to by matching the request scheme/host/port against the URL
 * column on `sales_channel_domain`.
 *
 * Storefront-only: we restrict to `Defaults::SALES_CHANNEL_TYPE_STOREFRONT`
 * so the discovery documents never describe an Admin or Headless channel.
 *
 * Falls back to host-only matching if no exact URL prefix matches (multi-domain
 * setups with explicit ports or trailing slashes).
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 *
 * @internal
 */
#[Package('framework')]
class AgenticDiscoveryDomainResolver
{
    /**
     * @param EntityRepository<SalesChannelDomainCollection> $salesChannelDomainRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelDomainRepository,
    ) {
    }

    public function resolve(Request $request, Context $context): ?SalesChannelDomainEntity
    {
        $scheme = $request->getScheme();
        $host = $request->getHost();
        $port = $request->getPort();

        $candidateUrl = $scheme . '://' . $host
            . ($port && $port !== 80 && $port !== 443 ? ':' . $port : '');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('url', $candidateUrl))
            ->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))
            ->setLimit(1);

        $entity = $this->salesChannelDomainRepository->search($criteria, $context)->first();
        if ($entity instanceof SalesChannelDomainEntity) {
            return $entity;
        }

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        foreach ($this->salesChannelDomainRepository->search($criteria, $context) as $domain) {
            \assert($domain instanceof SalesChannelDomainEntity);
            $parts = parse_url($domain->getUrl());
            if (!\is_array($parts)) {
                continue;
            }
            if (($parts['host'] ?? null) === $host) {
                return $domain;
            }
        }

        return null;
    }
}
