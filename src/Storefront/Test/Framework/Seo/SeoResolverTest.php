<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoResolver;
use Shopware\Storefront\Framework\Seo\SeoResolverInterface;

class SeoResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlRepository;

    /**
     * @var SeoResolverInterface
     */
    private $seoResolver;

    /**
     * @var string
     */
    private $deLanguageId;

    public function setUp(): void
    {
        $this->seoUrlRepository = $this->getContainer()->get('seo_url.repository');
        $this->seoResolver = $this->getContainer()->get(SeoResolver::class);

        $connection = $this->getContainer()->get(Connection::class);
        $connection->exec('DELETE FROM `sales_channel`');

        $this->deLanguageId = $this->getDeDeLanguageId();
    }

    public function testResolveEmpty(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannelId, '');
        static::assertEquals(['pathInfo' => '/', 'isCanonical' => false], $resolved);

        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannelId, '/');
        static::assertEquals(['pathInfo' => '/', 'isCanonical' => false], $resolved);

        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannelId, '//');
        static::assertEquals(['pathInfo' => '/', 'isCanonical' => false], $resolved);
    }

    public function testResolveSeoPathPassthrough(): void
    {
        $context = Context::createDefaultContext();
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannelId, '/foo/bar');
        static::assertEquals(['pathInfo' => '/foo/bar/', 'isCanonical' => false], $resolved);

        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannelId, 'foo/bar');
        static::assertEquals(['pathInfo' => '/foo/bar/', 'isCanonical' => false], $resolved);
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
                'isValid' => true,
                'isCanonical' => false,
            ],
            [
                'salesChannelId' => $salesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product-v2',
                'isValid' => true,
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        // pathInfo
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannelId, 'detail/1234');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(0, $resolved['isCanonical']);
        static::assertEquals('/awesome-product-v2', $resolved['canonicalPathInfo']);
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannelId, '/detail/1234');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(0, $resolved['isCanonical']);
        static::assertEquals('/awesome-product-v2', $resolved['canonicalPathInfo']);

        // old canonical
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannelId, 'awesome-product');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(0, $resolved['isCanonical']);
        static::assertEquals('/awesome-product-v2', $resolved['canonicalPathInfo']);
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannelId, '/awesome-product');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(0, $resolved['isCanonical']);
        static::assertEquals('/awesome-product-v2', $resolved['canonicalPathInfo']);

        // canonical
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannelId, 'awesome-product-v2');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(1, $resolved['isCanonical']);
        $resolved = $this->seoResolver->resolveSeoPath($context->getLanguageId(), $salesChannelId, '/awesome-product-v2');
        static::assertEquals('/detail/1234/', $resolved['pathInfo']);
        static::assertEquals(1, $resolved['isCanonical']);
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
                'isValid' => true,
                'isCanonical' => true,
            ],
            [
                'id' => $enId,
                'salesChannelId' => $salesChannelDeId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/1234',
                'seoPathInfo' => 'awesome-product-en',
                'isValid' => true,
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $actual = $this->seoResolver->resolveSeoPath($this->deLanguageId, $salesChannelDeId, 'awesome-product-de');
        static::assertEquals($deId, Uuid::fromBytesToHex($actual['id']));

        $actual = $this->seoResolver->resolveSeoPath(Defaults::LANGUAGE_SYSTEM, $salesChannelDeId, 'awesome-product-en');
        static::assertEquals($enId, Uuid::fromBytesToHex($actual['id']));
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
                'isValid' => true,
                'isCanonical' => true,
            ],
            [
                'id' => $otherId,
                'salesChannelId' => $otherSalesChannelId,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'routeName' => 'r',
                'pathInfo' => '/detail/other',
                'seoPathInfo' => 'awesome-product',
                'isValid' => true,
                'isCanonical' => true,
            ],
        ], Context::createDefaultContext());

        $unknownSalesChannelId = Uuid::randomHex();
        // returns default for unknown sales channels
        $actual = $this->seoResolver->resolveSeoPath(Defaults::LANGUAGE_SYSTEM, $unknownSalesChannelId, 'awesome-product');
        static::assertEquals('/detail/default/', $actual['pathInfo']);
        static::assertTrue((bool) $actual['isCanonical']);

        $actual = $this->seoResolver->resolveSeoPath(Defaults::LANGUAGE_SYSTEM, $otherSalesChannelId, 'awesome-product');
        static::assertEquals('/detail/other/', $actual['pathInfo']);
        static::assertTrue((bool) $actual['isCanonical']);
    }
}
