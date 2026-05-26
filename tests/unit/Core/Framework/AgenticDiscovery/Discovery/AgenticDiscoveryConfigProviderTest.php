<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AgenticDiscovery\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Collection\AgenticDiscoverySalesChannelConfigCollection;
use Shopware\Core\Framework\AgenticDiscovery\DataAbstractionLayer\Entity\AgenticDiscoverySalesChannelConfigEntity;
use Shopware\Core\Framework\AgenticDiscovery\Discovery\AgenticDiscoveryConfigProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(AgenticDiscoveryConfigProvider::class)]
class AgenticDiscoveryConfigProviderTest extends TestCase
{
    public function testReturnsConfigWhenFound(): void
    {
        $config = new AgenticDiscoverySalesChannelConfigEntity();
        $config->setUniqueIdentifier(Uuid::randomHex());

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn($this->makeResult([$config]));

        $provider = new AgenticDiscoveryConfigProvider($repository);
        static::assertSame($config, $provider->forSalesChannel('sc-1', Context::createDefaultContext()));
    }

    public function testReturnsNullWhenNotFound(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeResult([]));

        $provider = new AgenticDiscoveryConfigProvider($repository);
        static::assertNull($provider->forSalesChannel('sc-1', Context::createDefaultContext()));
    }

    public function testMemoizesResultForSameSalesChannelId(): void
    {
        $config = new AgenticDiscoverySalesChannelConfigEntity();
        $config->setUniqueIdentifier(Uuid::randomHex());

        $repository = $this->createMock(EntityRepository::class);
        // Expect EXACTLY one call across two forSalesChannel() invocations.
        $repository->expects($this->once())->method('search')->willReturn($this->makeResult([$config]));

        $provider = new AgenticDiscoveryConfigProvider($repository);
        $context = Context::createDefaultContext();

        static::assertSame($config, $provider->forSalesChannel('sc-1', $context));
        static::assertSame($config, $provider->forSalesChannel('sc-1', $context));
    }

    public function testClearRemovesMemoizedEntries(): void
    {
        $config = new AgenticDiscoverySalesChannelConfigEntity();
        $config->setUniqueIdentifier(Uuid::randomHex());

        $repository = $this->createMock(EntityRepository::class);
        // After clear(), the repository must be queried again.
        $repository->expects($this->exactly(2))->method('search')->willReturn($this->makeResult([$config]));

        $provider = new AgenticDiscoveryConfigProvider($repository);
        $context = Context::createDefaultContext();

        $provider->forSalesChannel('sc-1', $context);
        $provider->clear();
        $provider->forSalesChannel('sc-1', $context);
    }

    /**
     * @param list<AgenticDiscoverySalesChannelConfigEntity> $entities
     *
     * @return EntitySearchResult<AgenticDiscoverySalesChannelConfigCollection>
     */
    private function makeResult(array $entities): EntitySearchResult
    {
        $collection = new AgenticDiscoverySalesChannelConfigCollection($entities);

        return new EntitySearchResult(
            'agentic_discovery_sales_channel_config',
            \count($entities),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
