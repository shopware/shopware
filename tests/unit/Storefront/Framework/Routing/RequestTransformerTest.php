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

        yield 'punycode to punycode direct hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com',
            'serverVars' => [],
            'domainUrl' => 'http://xn--shpwre-eua5l.com',
            'expectedBaseUrl' => '',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedStorefrontUrl' => 'http://shöpwäre.com',
            'expectedResolvedUri' => '/',
        ];

        yield 'punycode to unicode direct hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com',
            'serverVars' => [],
            'domainUrl' => 'http://shöpwäre.com',
            'expectedBaseUrl' => '',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedStorefrontUrl' => 'http://shöpwäre.com',
            'expectedResolvedUri' => '/',
        ];

        yield 'punycode to punycode filter hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com/de/outdoor',
            'serverVars' => [],
            'domainUrl' => 'http://xn--shpwre-eua5l.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedStorefrontUrl' => 'http://shöpwäre.com/de',
            'expectedResolvedUri' => '/outdoor',
        ];

        yield 'punycode to unicode filter hit' => [
            'requestUrl' => 'http://xn--shpwre-eua5l.com/de/outdoor',
            'serverVars' => [],
            'domainUrl' => 'http://shöpwäre.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shöpwäre.com',
            'expectedStorefrontUrl' => 'http://shöpwäre.com/de',
            'expectedResolvedUri' => '/outdoor',
        ];

        yield 'virtual path before index.php' => [
            // see https://github.com/shopware/shopware/issues/6666
            'requestUrl' => 'http://shopware.com/de/index.php/navigation/abc',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php/navigation/abc',
            ],
            'domainUrl' => 'http://shopware.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopware.com',
            'expectedStorefrontUrl' => 'http://shopware.com/de',
            'expectedResolvedUri' => '/navigation/abc',
        ];

        yield 'virtual path before index.php in subdirectory' => [
            // see https://github.com/shopware/shopware/issues/6666
            'requestUrl' => 'http://shopware.com/public/de/index.php/navigation/abc',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/public/index.php',
                'PHP_SELF' => '/public/de/index.php/navigation/abc',
            ],
            'domainUrl' => 'http://shopware.com/public/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopware.com/public',
            'expectedStorefrontUrl' => 'http://shopware.com/public/de',
            'expectedResolvedUri' => '/navigation/abc',
        ];

        yield 'virtual path equals base url with index.php' => [
            // /de/index.php with no further path should resolve to the sales-channel home
            'requestUrl' => 'http://shopware.com/de/index.php',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php',
            ],
            'domainUrl' => 'http://shopware.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopware.com',
            'expectedStorefrontUrl' => 'http://shopware.com/de',
            'expectedResolvedUri' => '/',
        ];

        yield 'virtual path with trailing slash after index.php' => [
            // /de/index.php/ (trailing slash, no further path) should also resolve to home
            'requestUrl' => 'http://shopware.com/de/index.php/',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php/',
            ],
            'domainUrl' => 'http://shopware.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopware.com',
            'expectedStorefrontUrl' => 'http://shopware.com/de',
            'expectedResolvedUri' => '/',
        ];

        yield 'virtual path before custom front controller (app.php)' => [
            // ensure the strip uses basename($scriptName) and works for non-index.php front controllers
            'requestUrl' => 'http://shopware.com/de/app.php/navigation/abc',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/app.php',
                'SCRIPT_NAME' => '/app.php',
                'PHP_SELF' => '/de/app.php/navigation/abc',
            ],
            'domainUrl' => 'http://shopware.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopware.com',
            'expectedStorefrontUrl' => 'http://shopware.com/de',
            'expectedResolvedUri' => '/navigation/abc',
        ];

        yield 'slug with index.php prefix is preserved (boundary guard)' => [
            // a hypothetical SEO slug like "index.php-shop" must not be mangled by the strip;
            // the `$scriptName . '/'` suffix on str_starts_with ensures only the bare script
            // basename followed by a path separator is stripped.
            'requestUrl' => 'http://shopware.com/de/index.php-shop',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php-shop',
            ],
            'domainUrl' => 'http://shopware.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopware.com',
            'expectedStorefrontUrl' => 'http://shopware.com/de',
            'expectedResolvedUri' => '/index.php-shop',
        ];

        yield 'slug with index.php prefix is preserved when followed by sub-path' => [
            // an "index.php-shop" parent slug with a deeper path must also pass through unchanged.
            // The `$scriptName . '/'` boundary requires an exact script-name segment, so
            // "index.php-shop/foo" cannot be partial-stripped to "-shop/foo".
            'requestUrl' => 'http://shopware.com/de/index.php-shop/foo',
            'serverVars' => [
                'SCRIPT_FILENAME' => '/var/www/html/public/index.php',
                'SCRIPT_NAME' => '/index.php',
                'PHP_SELF' => '/de/index.php-shop/foo',
            ],
            'domainUrl' => 'http://shopware.com/de',
            'expectedBaseUrl' => '/de',
            'expectedAbsoluteBaseUrl' => 'http://shopware.com',
            'expectedStorefrontUrl' => 'http://shopware.com/de',
            'expectedResolvedUri' => '/index.php-shop/foo',
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
