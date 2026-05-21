<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Discovery;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Maps an incoming HTTP request to the SalesChannelDomain it belongs to by
 * comparing the request URI prefix against `sales_channel_domain.url`.
 *
 * Falls back to host-only matching if no domain url is an exact prefix match.
 *
 * @internal
 */
#[Package('framework')]
class SalesChannelDomainResolver
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

        $candidateUrls = [
            $scheme . '://' . $host . ($port && $port !== 80 && $port !== 443 ? ':' . $port : ''),
        ];

        // Direct prefix candidates
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('url', $candidateUrls[0]))
            ->setLimit(1);

        $entity = $this->salesChannelDomainRepository->search($criteria, $context)->first();
        if ($entity instanceof SalesChannelDomainEntity) {
            return $entity;
        }

        // Looser fallback: any domain whose host part matches
        $criteria = new Criteria();
        $allDomains = $this->salesChannelDomainRepository->search($criteria, $context);

        foreach ($allDomains as $domain) {
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
