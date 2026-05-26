<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AgenticDiscovery\Manifest;

use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Entity\AgenticDiscoverySalesChannelConfigEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;

/**
 * Read-only context handed to `DiscoverySectionProvider` implementations.
 * Bundles the resolved sales-channel domain (URL, language, currency,
 * snippet set) with the channel-specific discovery configuration so providers
 * can deliver section content without re-resolving the request scope.
 *
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_DISCOVERY
 */
#[Package('framework')]
class AgenticDiscoveryContext extends Struct
{
    public function __construct(
        private readonly SalesChannelDomainEntity $domain,
        private readonly AgenticDiscoverySalesChannelConfigEntity $config,
        private readonly Context $context,
    ) {
    }

    public function getDomain(): SalesChannelDomainEntity
    {
        return $this->domain;
    }

    public function getConfig(): AgenticDiscoverySalesChannelConfigEntity
    {
        return $this->config;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getDomainUrl(): string
    {
        return rtrim($this->domain->getUrl(), '/');
    }

    public function getSalesChannelId(): string
    {
        return $this->domain->getSalesChannelId();
    }

    public function getApiAlias(): string
    {
        return 'agentic_discovery_context';
    }
}
