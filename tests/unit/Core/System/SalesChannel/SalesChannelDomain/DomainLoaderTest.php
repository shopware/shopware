<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\SalesChannelDomain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelDomain\DomainLoader;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(DomainLoader::class)]
class DomainLoaderTest extends TestCase
{
    public function testLoadReturnsGroupedDomains(): void
    {
        $rows = $this->getDomainRows();
        $loader = $this->createDomainLoader($rows);

        $domains = $loader->load();

        static::assertArrayHasKey('https://example.com/', $domains);
        static::assertArrayHasKey('https://example.com/de/', $domains);

        $baseDomain = $domains['https://example.com/'];
        static::assertSame('https://example.com/', $baseDomain['url']);
        static::assertSame('sales-channel', $baseDomain['salesChannelId']);
        static::assertSame('snippet', $baseDomain['snippetSetId']);
        static::assertSame('currency', $baseDomain['currencyId']);
        static::assertSame('language', $baseDomain['languageId']);
        static::assertSame('en-GB', $baseDomain['locale']);
    }

    public function testFindDomainReturnsExactMatch(): void
    {
        $loader = $this->createDomainLoader($this->getDomainRows());

        $request = Request::create('https://example.com/' . StoreApiRouteScope::ID . '/path');
        $domain = $loader->findDomain($request);

        static::assertNotNull($domain);
        static::assertSame('https://example.com', $domain['url']);
    }

    public function testFindDomainReturnsBestMatchingPrefix(): void
    {
        $loader = $this->createDomainLoader($this->getDomainRows());

        $request = Request::create('https://example.com/de/further/path');
        $domain = $loader->findDomain($request);

        static::assertNotNull($domain);
        static::assertSame('https://example.com/de', $domain['url']);
    }

    public function testFindDomainReturnsNullWhenNoDomainMatchExists(): void
    {
        $loader = $this->createDomainLoader($this->getDomainRows());

        $request = Request::create('https://other.test/store-api');
        $domain = $loader->findDomain($request);

        static::assertNull($domain);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function createDomainLoader(array $rows): DomainLoader
    {
        return new DomainLoader(new FakeConnection($rows), new EventDispatcher());
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getDomainRows(): array
    {
        return [
            [
                'key' => 'https://example.com/',
                'url' => 'https://example.com/',
                'id' => 'domain-base',
                'salesChannelId' => 'sales-channel',
                'typeId' => 'type',
                'snippetSetId' => 'snippet',
                'currencyId' => 'currency',
                'languageId' => 'language',
                'maintenance' => '0',
                'maintenanceIpWhitelist' => '[]',
                'locale' => 'en-GB',
            ],
            [
                'key' => 'https://example.com/de/',
                'url' => 'https://example.com/de/',
                'id' => 'domain-de',
                'salesChannelId' => 'sales-channel',
                'typeId' => 'type',
                'snippetSetId' => 'snippet',
                'currencyId' => 'currency',
                'languageId' => 'language',
                'maintenance' => '0',
                'maintenanceIpWhitelist' => '[]',
                'locale' => 'de-DE',
            ],
        ];
    }
}
