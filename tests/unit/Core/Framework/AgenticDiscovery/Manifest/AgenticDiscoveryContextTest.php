<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AgenticDiscovery\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Entity\AgenticDiscoverySalesChannelConfigEntity;
use Shopware\Core\Framework\AgenticDiscovery\Manifest\AgenticDiscoveryContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;

/**
 * @internal
 */
#[CoversClass(AgenticDiscoveryContext::class)]
class AgenticDiscoveryContextTest extends TestCase
{
    public function testExposesDomainConfigAndContext(): void
    {
        $salesChannelId = Uuid::randomHex();

        $domain = new SalesChannelDomainEntity();
        $domain->setUniqueIdentifier(Uuid::randomHex());
        $domain->setUrl('https://shop.acme.test/de/');
        $domain->setSalesChannelId($salesChannelId);

        $config = new AgenticDiscoverySalesChannelConfigEntity();
        $config->setUniqueIdentifier(Uuid::randomHex());
        $config->setSalesChannelId($salesChannelId);

        $shopwareContext = Context::createDefaultContext();
        $context = new AgenticDiscoveryContext($domain, $config, $shopwareContext);

        static::assertSame($domain, $context->getDomain());
        static::assertSame($config, $context->getConfig());
        static::assertSame($shopwareContext, $context->getContext());
        static::assertSame($salesChannelId, $context->getSalesChannelId());
    }

    public function testGetDomainUrlTrimsTrailingSlash(): void
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setUniqueIdentifier(Uuid::randomHex());
        $domain->setUrl('https://shop.acme.test/de/');
        $domain->setSalesChannelId(Uuid::randomHex());

        $context = new AgenticDiscoveryContext(
            $domain,
            new AgenticDiscoverySalesChannelConfigEntity(),
            Context::createDefaultContext(),
        );

        static::assertSame('https://shop.acme.test/de', $context->getDomainUrl());
    }

    public function testHasStableApiAlias(): void
    {
        $context = new AgenticDiscoveryContext(
            new SalesChannelDomainEntity(),
            new AgenticDiscoverySalesChannelConfigEntity(),
            Context::createDefaultContext(),
        );

        static::assertSame('agentic_discovery_context', $context->getApiAlias());
    }
}
