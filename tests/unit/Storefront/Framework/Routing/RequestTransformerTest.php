<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\AbstractSeoResolver;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Framework\Routing\AbstractDomainLoader;
use Shopware\Storefront\Framework\Routing\Exception\SalesChannelMappingException;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RequestTransformer::class)]
class RequestTransformerTest extends TestCase
{
    /**
     * @param list<string> $registeredApiPrefixes
     */
    #[DataProvider('notRequiredSalesChannelProvider')]
    public function testSalesChannelIsNotRequired(array $registeredApiPrefixes, string $requestUri): void
    {
        $decorated = $this->createMock(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = $this->createMock(AbstractSeoResolver::class);
        $domainLoader = $this->createMock(AbstractDomainLoader::class);

        // should not be called as the sales channel is not required
        $domainLoader->expects($this->never())->method('load');

        $requestTransformer = new RequestTransformer($decorated, $resolver, $registeredApiPrefixes, $domainLoader);

        $originalRequest = Request::create($requestUri);
        $transformedRequest = $requestTransformer->transform($originalRequest);

        static::assertSame($originalRequest, $transformedRequest);
    }

    public function testSalesChannelIsRequired(): void
    {
        $decorated = $this->createMock(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = $this->createMock(AbstractSeoResolver::class);
        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader->expects($this->once())->method('load')->willReturn([]);

        // no registered api prefixes ==> sales channel is always required
        $registeredApiPrefixes = [];
        $requestTransformer = new RequestTransformer($decorated, $resolver, $registeredApiPrefixes, $domainLoader);

        $originalRequest = Request::create('http://shopware.com/api');

        static::expectException(SalesChannelMappingException::class);
        $requestTransformer->transform($originalRequest);
    }

    /**
     * @param array<string, string> $serverVars
     */
    #[DataProvider('transformRequestProvider')]
    public function testTransformUsesBasePathInsteadOfBaseUrl(
        string $requestUrl,
        array $serverVars,
        string $domainUrl,
        string $expectedBaseUrl,
        string $expectedAbsoluteBaseUrl,
        string $expectedStorefrontUrl,
        string $expectedResolvedUri,
    ): void {
        $domainId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $snippetSetId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $themeId = Uuid::randomHex();

        $domainKey = rtrim($domainUrl, '/') . '/';

        $decorated = $this->createMock(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(static fn ($request) => $request);

        $resolver = $this->createMock(AbstractSeoResolver::class);
        $resolver->method('resolve')->willReturnCallback(static fn ($langId, $scId, $seoPathInfo) => [
            'pathInfo' => '/' . ltrim($seoPathInfo, '/'),
            'isCanonical' => false,
        ]);

        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader->method('load')->willReturn([
            $domainKey => [
                'url' => $domainKey,
                'id' => $domainId,
                'salesChannelId' => $salesChannelId,
                'typeId' => 'storefront',
                'snippetSetId' => $snippetSetId,
                'currencyId' => $currencyId,
                'languageId' => $languageId,
                'themeId' => $themeId,
                'maintenance' => '0',
                'maintenanceIpWhitelist' => '',
                'locale' => 'en-GB',
                'themeName' => 'Storefront',
                'parentThemeName' => '',
            ],
        ]);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [ApiRouteScope::ID], $domainLoader);

        $request = Request::create($requestUrl, 'GET', [], [], [], $serverVars);
        $transformed = $requestTransformer->transform($request);

        static::assertSame($expectedBaseUrl, $transformed->attributes->get(RequestTransformer::SALES_CHANNEL_BASE_URL));
        static::assertSame($expectedAbsoluteBaseUrl, $transformed->attributes->get(RequestTransformer::SALES_CHANNEL_ABSOLUTE_BASE_URL));
        static::assertSame($expectedStorefrontUrl, $transformed->attributes->get(RequestTransformer::STOREFRONT_URL));
        static::assertSame($expectedResolvedUri, $transformed->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI));
        static::assertSame($salesChannelId, $transformed->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID));
        static::assertTrue($transformed->attributes->get(SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST));
    }

    /**
     * @return iterable<string, array{requestUrl: string, serverVars: array<string, string>, domainUrl: string, expectedBaseUrl: string, expectedAbsoluteBaseUrl: string, expectedStorefrontUrl: string, expectedResolvedUri: string}>
     */
    public static function transformRequestProvider(): iterable
    {
        yield 'index.php at root' => [
            'requestUrl' => 'http://shopware.com/index.php',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/index.php',
            ],
            'domainUrl' => 'http://shopware.com',
            'expectedBaseUrl' => '',
            'expectedAbsoluteBaseUrl' => 'http://shopware.com',
            'expectedStorefrontUrl' => 'http://shopware.com',
            'expectedResolvedUri' => '/',
        ];

        yield 'index.php with virtual path and page' => [
            'requestUrl' => 'http://shopware.com/index.php/de/outdoor',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/index.php/de/outdoor',
            ],
            'domainUrl' => 'http://shopware.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopware.com',
            'expectedStorefrontUrl' => 'http://shopware.com/de',
            'expectedResolvedUri' => '/outdoor',
        ];

        yield 'index.php in subdirectory' => [
            'requestUrl' => 'http://shopware.com/public/index.php/de',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/public/index.php',
                'PHP_SELF' => '/public/index.php/de',
            ],
            'domainUrl' => 'http://shopware.com/public/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopware.com/public',
            'expectedStorefrontUrl' => 'http://shopware.com/public/de',
            'expectedResolvedUri' => '/',
        ];

        yield 'normal request without index.php' => [
            'requestUrl' => 'http://shopware.com/de/outdoor',
            'serverVars' => [],
            'domainUrl' => 'http://shopware.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopware.com',
            'expectedStorefrontUrl' => 'http://shopware.com/de',
            'expectedResolvedUri' => '/outdoor',
        ];
    }

    /**
     * @return iterable<string, array{registeredApiPrefixes: list<string>, requestUri: string}>
     */
    public static function notRequiredSalesChannelProvider(): iterable
    {
        yield 'Default case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopware.com/api',
        ];

        yield 'Case with trailing slash' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopware.com/api/',
        ];

        yield 'Case with double leading slashes' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopware.com//api',
        ];

        yield 'Case with double trailing slashes' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopware.com/api//',
        ];

        yield 'Case with double leading and trailing slashes' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopware.com//api//',
        ];

        // Allowedlist paths:
        yield '_wdt case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopware.com/_wdt/',
        ];

        yield '_profiler case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopware.com/_profiler/',
        ];

        yield '_error case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopware.com/_error/',
        ];

        yield 'payment finalize-transaction case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopware.com/payment/finalize-transaction/',
        ];

        yield 'installer case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopware.com/installer',
        ];

        yield '_fragment case' => [
            'registeredApiPrefixes' => [ApiRouteScope::ID],
            'requestUri' => 'http://shopware.com/_fragment/',
        ];
    }
}
