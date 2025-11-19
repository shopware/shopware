<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\RequestTransformer;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelDomain\AbstractDomainLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RequestTransformer::class)]
class RequestTransformerTest extends TestCase
{
    public function testTransformIgnoresNonStoreApiRequests(): void
    {
        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader->expects($this->never())->method('findDomain');

        $transformer = new RequestTransformer($domainLoader);

        $request = Request::create('https://example.com/api');
        $result = $transformer->transform($request);

        static::assertSame($request, $result);
        static::assertFalse($result->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_STORE_API_REQUEST));
    }

    public function testTransformWithoutMatchingDomainLeavesRequestUntouched(): void
    {
        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader->expects($this->once())->method('findDomain')->willReturn(null);

        $transformer = new RequestTransformer($domainLoader);

        $request = Request::create('https://example.com/' . StoreApiRouteScope::ID . '/foo');
        $result = $transformer->transform($request);

        static::assertSame($request, $result);
        static::assertFalse($result->attributes->has(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST));
    }

    public function testTransformEnrichesRequestWithDomainInformation(): void
    {
        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader->expects($this->once())->method('findDomain')->willReturn($this->createDomain());

        $transformer = new RequestTransformer($domainLoader);

        $request = Request::create('https://example.com/' . StoreApiRouteScope::ID . '/foo/bar');
        $result = $transformer->transform($request);

        static::assertTrue($result->attributes->getBoolean(SalesChannelRequest::ATTRIBUTE_IS_STORE_API_REQUEST));
        static::assertSame('en-GB', $result->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_LOCALE));
        static::assertSame('snippet', $result->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_SNIPPET_SET_ID));
        static::assertSame('currency', $result->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID));
        static::assertSame('domain-id', $result->attributes->get(SalesChannelRequest::ATTRIBUTE_DOMAIN_ID));
        static::assertTrue($result->attributes->getBoolean(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE));
        static::assertSame(['127.0.0.1'], $result->attributes->get(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITELIST));
        static::assertSame('language', $result->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    public function testTransformDoesNotOverrideExistingLanguageHeader(): void
    {
        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader->method('findDomain')->willReturn($this->createDomain(['languageId' => 'domain-language']));

        $transformer = new RequestTransformer($domainLoader);

        $request = Request::create('https://example.com/' . StoreApiRouteScope::ID . '/foo/bar');
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, 'preset-language');

        $result = $transformer->transform($request);

        static::assertSame('preset-language', $result->headers->get(PlatformRequest::HEADER_LANGUAGE_ID));
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function createDomain(array $overrides = []): array
    {
        $domain = [
            'url' => 'https://example.com/',
            'id' => 'domain-id',
            'salesChannelId' => 'sales-channel-id',
            'typeId' => 'type',
            'snippetSetId' => 'snippet',
            'currencyId' => 'currency',
            'languageId' => 'language',
            'themeId' => 'theme',
            'maintenance' => '1',
            'maintenanceIpWhitelist' => ['127.0.0.1'],
            'locale' => 'en-GB',
            'themeName' => 'Theme',
            'parentThemeName' => 'ParentTheme',
        ];

        return array_merge($domain, $overrides);
    }
}
