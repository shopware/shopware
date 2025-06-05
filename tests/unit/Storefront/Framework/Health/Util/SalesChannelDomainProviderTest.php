<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health\Util;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Storefront\Framework\SystemCheck\Util\AbstractSalesChannelDomainProvider;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomain;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainProvider;

/**
 * @internal
 */
#[CoversClass(SalesChannelDomainProvider::class)]
class SalesChannelDomainProviderTest extends TestCase
{
    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
    }

    public function testFetchSalesChannelDomainsReturnsCollectionWithData(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['sales_channel_id' => 'test-sales-channel-id-1', 'url' => 'http://localhost:8000'],
            ['sales_channel_id' => 'test-sales-channel-id-2', 'url' => 'http://localhost:8001'],
        ]);

        $provider = $this->createProvider();

        $collection = $provider->fetchSalesChannelDomains();
        static::assertCount(2, $collection);
        static::assertContainsOnlyInstancesOf(SalesChannelDomain::class, $collection);
    }

    public function testFetchSalesChannelDomainsHandlesEmptyResults(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $provider = $this->createProvider();

        $collection = $provider->fetchSalesChannelDomains();
        static::assertCount(0, $collection);
    }

    private function createProvider(): AbstractSalesChannelDomainProvider
    {
        return new SalesChannelDomainProvider($this->connection);
    }
}
