<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Seo;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\AbstractSeoResolver;
use Shopware\Core\Content\Seo\SeoResolver;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
class SeoResolverQueryParameterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    private EntityRepository $seoUrlRepository;

    private AbstractSeoResolver $seoResolver;

    protected function setUp(): void
    {
        $this->seoUrlRepository = static::getContainer()->get('seo_url.repository');
        $this->seoResolver = static::getContainer()->get(SeoResolver::class);
    }

    public function testResolveWithQueryParameters(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        // Create a SEO URL without query parameters
        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.detail.page',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'Main-product/SWDEMO10001',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        // Test that SEO URL with query parameters resolves correctly
        // The query parameters should not interfere with the SEO path resolution
        $resolved = $this->seoResolver->resolve($context->getLanguageId(), $salesChannelId, 'Main-product/SWDEMO10001?test=123');
        
        static::assertSame('/detail/1234', $resolved['pathInfo']);
        static::assertTrue((bool) $resolved['isCanonical']);
        static::assertArrayNotHasKey('canonicalPathInfo', $resolved);
    }

    public function testResolveWithMultipleQueryParameters(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        // Create a SEO URL without query parameters
        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.detail.page',
                'pathInfo' => '/detail/5678',
                'seoPathInfo' => 'awesome-product',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        // Test that SEO URL with multiple query parameters resolves correctly
        $resolved = $this->seoResolver->resolve($context->getLanguageId(), $salesChannelId, 'awesome-product?param1=value1&param2=value2');
        
        static::assertSame('/detail/5678', $resolved['pathInfo']);
        static::assertTrue((bool) $resolved['isCanonical']);
        static::assertArrayNotHasKey('canonicalPathInfo', $resolved);
    }

    public function testResolveWithQueryParametersAndSpecialCharacters(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        // Create a SEO URL without query parameters
        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.detail.page',
                'pathInfo' => '/detail/9999',
                'seoPathInfo' => 'special-product',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        // Test that SEO URL with query parameters containing special characters resolves correctly
        $resolved = $this->seoResolver->resolve($context->getLanguageId(), $salesChannelId, 'special-product?search=test%20product&filter[]=category');
        
        static::assertSame('/detail/9999', $resolved['pathInfo']);
        static::assertTrue((bool) $resolved['isCanonical']);
        static::assertArrayNotHasKey('canonicalPathInfo', $resolved);
    }

    public function testResolveWithQueryParametersButNoMatchingSeoUrl(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        // Don't create any SEO URLs - this should fall back to the passed path
        $resolved = $this->seoResolver->resolve($context->getLanguageId(), $salesChannelId, 'non-existent-product?test=123');
        
        // Should fall back to the original path (without query parameters)
        static::assertSame('/non-existent-product', $resolved['pathInfo']);
        static::assertFalse($resolved['isCanonical']);
    }

    public function testResolveWithEmptyQueryParameter(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        // Create a SEO URL without query parameters
        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'frontend.detail.page',
                'pathInfo' => '/detail/1111',
                'seoPathInfo' => 'empty-query-product',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        // Test SEO URL with empty query parameter
        $resolved = $this->seoResolver->resolve($context->getLanguageId(), $salesChannelId, 'empty-query-product?');
        
        static::assertSame('/detail/1111', $resolved['pathInfo']);
        static::assertTrue((bool) $resolved['isCanonical']);
    }

    public function testResolveWithOnlyQuery(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        // Test path that is only a query parameter
        $resolved = $this->seoResolver->resolve($context->getLanguageId(), $salesChannelId, '?test=123');
        
        // Should fall back to root path
        static::assertSame('/', $resolved['pathInfo']);
        static::assertFalse($resolved['isCanonical']);
    }

    public function testResolveWithSlashAndQuery(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        // Test path with leading slash and query parameter
        $resolved = $this->seoResolver->resolve($context->getLanguageId(), $salesChannelId, '/some/path?test=123');
        
        // Should fall back to the path without query parameters
        static::assertSame('/some/path', $resolved['pathInfo']);
        static::assertFalse($resolved['isCanonical']);
    }
}