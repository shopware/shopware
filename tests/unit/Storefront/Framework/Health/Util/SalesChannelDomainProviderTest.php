<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Health\Util;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\SystemCheck\Util\AbstractSalesChannelDomainProvider;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomain;
use Shopware\Storefront\Framework\SystemCheck\Util\SalesChannelDomainProvider;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelDomainProvider::class)]
class SalesChannelDomainProviderTest extends TestCase
{
    private Connection&Stub $connection;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
    }

    public function testFetchSalesChannelDomainsReturnsCollectionWithData(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['id' => 'domain-1', 'sales_channel_id' => 'test-sales-channel-id-1', 'url' => 'http://localhost:8000', 'language_id' => 'language-1', 'currency_id' => 'currency-1'],
            ['id' => 'domain-2', 'sales_channel_id' => 'test-sales-channel-id-2', 'url' => 'http://localhost:8001', 'language_id' => 'language-2', 'currency_id' => 'currency-2'],
        ]);

        $provider = $this->createProvider();

        $collection = $provider->fetchSalesChannelDomains();
        static::assertCount(2, $collection);
        static::assertContainsOnlyInstancesOf(SalesChannelDomain::class, $collection);

        // the readiness checks build their lookup context from these, so the query has to select them
        $domain = $collection->get('test-sales-channel-id-1');
        static::assertInstanceOf(SalesChannelDomain::class, $domain);
        static::assertSame('domain-1', $domain->id);
        static::assertSame('language-1', $domain->languageId);
        static::assertSame('currency-1', $domain->currencyId);
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
