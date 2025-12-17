<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\SalesChannelDomain;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
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

    public function testFindDomainUsesSwDomainHeaderWhenProvided(): void
    {
        $loader = $this->createDomainLoader($this->getDomainRows());

        // Request is to different domain but sw-domain header points to configured domain
        $request = Request::create('https://other.test/store-api');
        $request->headers->set(PlatformRequest::HEADER_DOMAIN, 'https://example.com/de');

        $domain = $loader->findDomain($request);

        static::assertNotNull($domain);
        static::assertSame('https://example.com/de', $domain['url']);
        static::assertSame('de-DE', $domain['locale']);
    }

    public function testFindDomainUsesSwDomainHeaderWithTrailingSlash(): void
    {
        $loader = $this->createDomainLoader($this->getDomainRows());

        $request = Request::create('https://other.test/store-api');
        $request->headers->set(PlatformRequest::HEADER_DOMAIN, 'https://example.com/de/');

        $domain = $loader->findDomain($request);

        static::assertNotNull($domain);
        static::assertSame('https://example.com/de', $domain['url']);
    }

    public function testFindDomainPrefersSwDomainHeaderOverRequestUrl(): void
    {
        $loader = $this->createDomainLoader($this->getDomainRows());

        // Request URL points to base domain but header points to /de
        $request = Request::create('https://example.com/store-api');
        $request->headers->set(PlatformRequest::HEADER_DOMAIN, 'https://example.com/de');

        $domain = $loader->findDomain($request);

        static::assertNotNull($domain);
        static::assertSame('https://example.com/de', $domain['url']);
        static::assertSame('de-DE', $domain['locale']);
    }

    public function testFindDomainFallsBackToRequestUrlWhenSwDomainHeaderNotProvided(): void
    {
        $loader = $this->createDomainLoader($this->getDomainRows());

        // Without sw-domain header, uses the request URL for matching
        $request = Request::create('https://example.com/store-api/product');

        $domain = $loader->findDomain($request);

        static::assertNotNull($domain);
        static::assertSame('https://example.com', $domain['url']);
    }

    public function testFindDomainReturnsNullWhenSwDomainHeaderDoesNotMatchAnyDomain(): void
    {
        $loader = $this->createDomainLoader($this->getDomainRows());

        $request = Request::create('https://example.com/store-api');
        $request->headers->set(PlatformRequest::HEADER_DOMAIN, 'https://nonexistent.test');

        $domain = $loader->findDomain($request);

        static::assertNull($domain);
    }

    public function testFindDomainSupportsHeadlessFrontendOnDifferentDomain(): void
    {
        $loader = $this->createDomainLoader($this->getDomainRows());

        // Headless frontend on localhost calls Store API on different domain
        // but specifies which Shopware domain configuration to use via sw-domain header
        $request = Request::create('https://api.backend.example.com/store-api/product');
        $request->headers->set(PlatformRequest::HEADER_DOMAIN, 'https://example.com/de');

        $domain = $loader->findDomain($request);

        static::assertNotNull($domain);
        static::assertSame('https://example.com/de', $domain['url']);
        static::assertSame('de-DE', $domain['locale']);
        static::assertSame('currency', $domain['currencyId']);
        static::assertSame('language', $domain['languageId']);
    }

    public function testFindDomainSupportsSubdomainConfiguration(): void
    {
        $rows = [
            [
                'key' => 'https://en.shop.example.com/',
                'url' => 'https://en.shop.example.com/',
                'id' => 'domain-en',
                'salesChannelId' => 'sales-channel',
                'typeId' => 'type',
                'snippetSetId' => 'snippet-en',
                'currencyId' => 'currency-usd',
                'languageId' => 'language-en',
                'maintenance' => '0',
                'maintenanceIpWhitelist' => '[]',
                'locale' => 'en-US',
            ],
            [
                'key' => 'https://de.shop.example.com/',
                'url' => 'https://de.shop.example.com/',
                'id' => 'domain-de',
                'salesChannelId' => 'sales-channel',
                'typeId' => 'type',
                'snippetSetId' => 'snippet-de',
                'currencyId' => 'currency-eur',
                'languageId' => 'language-de',
                'maintenance' => '0',
                'maintenanceIpWhitelist' => '[]',
                'locale' => 'de-DE',
            ],
        ];

        $loader = $this->createDomainLoader($rows);

        // Headless frontend specifies subdomain via sw-domain header
        $request = Request::create('https://api.example.com/store-api/product');
        $request->headers->set(PlatformRequest::HEADER_DOMAIN, 'https://de.shop.example.com');

        $domain = $loader->findDomain($request);

        static::assertNotNull($domain);
        static::assertSame('https://de.shop.example.com', $domain['url']);
        static::assertSame('de-DE', $domain['locale']);
        static::assertSame('currency-eur', $domain['currencyId']);
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
