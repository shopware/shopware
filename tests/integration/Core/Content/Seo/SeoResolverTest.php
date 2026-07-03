<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\AbstractSeoResolver;
use Shopware\Core\Content\Seo\SeoResolver;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrlRequestContext;
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
class SeoResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    private AbstractSeoResolver $seoResolver;

    private string $deLanguageId;

    protected function setUp(): void
    {
        $this->seoUrlRepository = static::getContainer()->get('seo_url.repository');
        $this->seoResolver = static::getContainer()->get(SeoResolver::class);

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `sales_channel`');

        $this->deLanguageId = $this->getDeDeLanguageId();
    }

    public function testResolveEmpty(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $salesChannelId, ''));
        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $salesChannelId, '/'));
        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $salesChannelId, '//'));
        static::assertSame('/', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
    }

    public function testResolveSeoPathPassthrough(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $salesChannelId, '/foo/bar'));
        static::assertSame('/foo/bar', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $salesChannelId, 'foo/bar'));
        static::assertSame('/foo/bar', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
    }

    public function testResolveSeoPath(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product',
                'isCanonical' => false,
            ],
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product-v2',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $languageId = $context->getLanguageId();

        // pathInfo
        foreach (['detail/1234', '/detail/1234', 'detail/1234/', '/detail/1234/'] as $path) {
            $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($languageId, $salesChannelId, $path));
            static::assertSame('/detail/1234', $resolved->pathInfo);
            static::assertFalse($resolved->isCanonical);
            static::assertSame('/awesome-product-v2', $resolved->canonicalPathInfo);
        }

        // old canonical
        foreach (['awesome-product', '/awesome-product', 'awesome-product/', '/awesome-product/'] as $path) {
            $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($languageId, $salesChannelId, $path));
            static::assertSame('/detail/1234', $resolved->pathInfo);
            static::assertFalse($resolved->isCanonical);
            static::assertSame('/awesome-product-v2', $resolved->canonicalPathInfo);
        }

        // canonical
        foreach (['awesome-product-v2', '/awesome-product-v2', 'awesome-product-v2/', '/awesome-product-v2/'] as $path) {
            $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($languageId, $salesChannelId, $path));
            static::assertSame('/detail/1234', $resolved->pathInfo);
            static::assertTrue($resolved->isCanonical);
        }
    }

    public function testResolveSeoPathWithCanonicalIsNull(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product',
                'isCanonical' => null,
            ],
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product/',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $salesChannelId, '/awesome-product/'));
        static::assertSame('/detail/1234', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
    }

    public function testResolveCanonMultiLang(): void
    {
        $salesChannelDeId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext(
            $salesChannelDeId,
            'de',
            $this->deLanguageId,
            [Defaults::LANGUAGE_SYSTEM, $this->deLanguageId]
        );

        $deId = Uuid::randomHex();
        $enId = Uuid::randomHex();

        $this->seoUrlRepository->create([
            [
                'id' => $deId,
                'salesChannelId' => $salesChannelDeId,
                'languageId' => $this->deLanguageId,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product-de',
                'isCanonical' => true,
            ],
            [
                'id' => $enId,
                'salesChannelId' => $salesChannelDeId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product-en',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $actual = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($this->deLanguageId, $salesChannelDeId, 'awesome-product-de'));
        static::assertNotNull($actual->id);
        static::assertSame($deId, Uuid::fromBytesToHex($actual->id));

        $actual = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, $salesChannelDeId, 'awesome-product-en'));
        static::assertNotNull($actual->id);
        static::assertSame($enId, Uuid::fromBytesToHex($actual->id));
    }

    public function testResolveSamePathForDifferentSalesChannels(): void
    {
        $otherSalesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext(
            $otherSalesChannelId,
            'de',
            $this->deLanguageId,
            [Defaults::LANGUAGE_SYSTEM, $this->deLanguageId]
        );

        $defaultId = Uuid::randomHex();
        $otherId = Uuid::randomHex();

        $this->seoUrlRepository->create([
            [
                'id' => $defaultId,
                'salesChannelId' => null, // default
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/default',
                'seoPathInfo' => 'awesome-product',
                'isCanonical' => true,
            ],
            [
                'id' => $otherId,
                'salesChannelId' => $otherSalesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/other',
                'seoPathInfo' => 'awesome-product',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $unknownSalesChannelId = Uuid::randomHex();
        // returns default for unknown sales channels
        $actual = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, $unknownSalesChannelId, 'awesome-product'));
        static::assertSame('/detail/default', $actual->pathInfo);
        static::assertTrue($actual->isCanonical);

        $actual = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, $otherSalesChannelId, 'awesome-product'));
        static::assertSame('/detail/other', $actual->pathInfo);
        static::assertTrue($actual->isCanonical);
    }

    public function testSalesChannelSpecificSeoulWillBePrioritized(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => null, // default
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/default',
                'seoPathInfo' => 'awesome-product',
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/sales-channel',
                'seoPathInfo' => 'awesome-product',
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $salesChannelResponse = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, $salesChannelId, 'awesome-product'));
        static::assertSame('/sales-channel', $salesChannelResponse->pathInfo);

        $salesChannelResponse = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(Defaults::LANGUAGE_SYSTEM, Uuid::randomHex(), 'awesome-product'));
        static::assertSame('/default', $salesChannelResponse->pathInfo);
    }

    public function testResolveSeoPathWithCanonicalContainingQueryString(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/12345',
                'seoPathInfo' => 'Main-product/SWDEMO10001?test=123',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $salesChannelId,
            'Main-product/SWDEMO10001',
            'test=123',
        ));

        static::assertSame('/detail/12345', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveSeoPathWithPercentEncodedCharacter(): void
    {
        // Valid percent-escapes (e.g. "café" slugified to "caf%C3%A9") are kept storable by the
        // SEO path validation (see fix/seo-url-percent-400-13796); the resolver must therefore be
        // able to look them up verbatim. getPathInfo() keeps the path percent-encoded, so the
        // stored seo_path_info is compared as-is.
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/cafe',
                'seoPathInfo' => 'caf%C3%A9',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $salesChannelId,
            'caf%C3%A9',
        ));

        static::assertSame('/detail/cafe', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveSeoPathWithPercentEncodedQueryValue(): void
    {
        // A query-bearing SEO URL whose value contains a valid percent-escape ("ref=a%20b") must
        // resolve without triggering a canonical redirect. The raw request query is matched verbatim
        // against the stored seo_path_info, so the escape round-trips correctly.
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/12345',
                'seoPathInfo' => 'Main-product/SWDEMO10001?ref=a%20b',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $salesChannelId,
            'Main-product/SWDEMO10001',
            'ref=a%20b',
        ));

        static::assertSame('/detail/12345', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveSeoPathWithDifferentQueryStringDoesNotMatchCanonicalQuery(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/12345',
                'seoPathInfo' => 'Main-product/SWDEMO10001?test=123',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $salesChannelId,
            'Main-product/SWDEMO10001',
            'test=12334',
        ));

        static::assertSame('/Main-product/SWDEMO10001', $resolved->pathInfo);
        static::assertFalse($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveSeoPathWithQueryStringPrefersExactVariantMatch(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/base',
                'seoPathInfo' => 'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65',
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/variant-b',
                'seoPathInfo' => 'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65?test=5.2',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $salesChannelId,
            'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65',
            'test=5.2',
        ));

        static::assertSame('/detail/variant-b', $resolved->pathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveSeoPathWithDifferentQueryValueFallsBackToPlainCanonical(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/base',
                'seoPathInfo' => 'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65',
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/variant-54',
                'seoPathInfo' => 'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65?test=5.4',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext(
            $context->getLanguageId(),
            $salesChannelId,
            'Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65',
            'test=5.42',
        ));

        static::assertSame('/detail/base', $resolved->pathInfo);
        static::assertSame('Aerodynamic-Aluminum-Chin-Up/SW-019d22fd316872bb96162ee6016a6c65', $resolved->seoPathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }

    public function testResolveWithoutQueryPrefersPlainCanonicalOverQueryVariant(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $this->seoUrlRepository->create([
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/query',
                'seoPathInfo' => 'Main-product/SWDEMO10001?test=123',
                'isCanonical' => true,
            ],
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/plain',
                'seoPathInfo' => 'Main-product/SWDEMO10001',
                'isCanonical' => true,
            ],
        ], $context);

        $resolved = $this->seoResolver->resolveUrl(new SeoUrlRequestContext($context->getLanguageId(), $salesChannelId, 'Main-product/SWDEMO10001'));

        static::assertSame('/detail/plain', $resolved->pathInfo);
        static::assertSame('Main-product/SWDEMO10001', $resolved->seoPathInfo);
        static::assertTrue($resolved->isCanonical);
        static::assertNull($resolved->canonicalPathInfo);
    }
}
