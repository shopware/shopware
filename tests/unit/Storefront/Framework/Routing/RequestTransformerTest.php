<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\AbstractSeoResolver;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Storefront\Framework\Routing\AbstractDomainLoader;
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
        $decorated->method('transform')->willReturnCallback(fn ($request) => $request);

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
        $decorated->method('transform')->willReturnCallback(fn ($request) => $request);

        $resolver = $this->createMock(AbstractSeoResolver::class);
        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader->expects($this->once())->method('load')->willReturn([]);

        // no registered api prefixes ==> sales channel is always required
        $registeredApiPrefixes = [];
        $requestTransformer = new RequestTransformer($decorated, $resolver, $registeredApiPrefixes, $domainLoader);

        $originalRequest = Request::create('http://shopware.com/api');

        static::expectException(RoutingException::class);
        $requestTransformer->transform($originalRequest);
    }

    public function testResolverReceivesQueryStringForExactMatching(): void
    {
        $decorated = $this->createMock(RequestTransformerInterface::class);
        $decorated->method('transform')->willReturnCallback(fn ($request) => $request);

        $languageId = bin2hex(random_bytes(16));
        $salesChannelId = bin2hex(random_bytes(16));

        $resolver = $this->createMock(AbstractSeoResolver::class);
        $resolver
            ->expects($this->once())
            ->method('resolve')
            ->with(
                $languageId,
                $salesChannelId,
                'Main-product/SWDEMO10001',
                'test=123'
            )
            ->willReturn([
                'pathInfo' => '/detail/123',
                'isCanonical' => true,
            ]);

        $domainLoader = $this->createMock(AbstractDomainLoader::class);
        $domainLoader
            ->expects($this->once())
            ->method('load')
            ->willReturn([
                // keys are normalized with trailing slash in RequestTransformer
                'http://shopware.com/' => [
                    'url' => 'http://shopware.com',
                    'id' => bin2hex(random_bytes(16)),
                    'salesChannelId' => $salesChannelId,
                    'typeId' => bin2hex(random_bytes(16)),
                    'snippetSetId' => bin2hex(random_bytes(16)),
                    'currencyId' => bin2hex(random_bytes(16)),
                    'languageId' => $languageId,
                    'themeId' => bin2hex(random_bytes(16)),
                    'maintenance' => '0',
                    'maintenanceIpWhitelist' => '',
                    'locale' => 'en-GB',
                    'themeName' => 'Storefront',
                    'parentThemeName' => '',
                ],
            ]);

        $requestTransformer = new RequestTransformer($decorated, $resolver, [], $domainLoader);

        $originalRequest = Request::create('http://shopware.com/Main-product/SWDEMO10001?test=123');
        $transformedRequest = $requestTransformer->transform($originalRequest);

        static::assertSame('/detail/123', $transformedRequest->attributes->get(RequestTransformer::SALES_CHANNEL_RESOLVED_URI));
    }

    /**
     * @return array<string, array{registeredApiPrefixes: list<string>, requestUri: string}>
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
