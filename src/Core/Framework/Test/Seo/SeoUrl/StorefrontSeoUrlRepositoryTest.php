<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Seo\SeoUrl;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;

/**
 * @internal
 */
class StorefrontSeoUrlRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var EntityRepository
     */
    private $seoUrlRepository;

    /**
     * @var SalesChannelRepository
     */
    private $salesChannelSeoUrlRepository;

    protected function setUp(): void
    {
        $this->seoUrlRepository = $this->getContainer()->get('seo_url.repository');
        $this->salesChannelSeoUrlRepository = $this->getContainer()->get('sales_channel.seo_url.repository');
    }

    public function testOnlyCanonical(): void
    {
        $canonicalId = Uuid::randomHex();
        $oldId = Uuid::randomHex();
        $foreignKey = Uuid::randomHex();

        $salesChannelContext = $this->createStorefrontSalesChannelContext(Uuid::randomHex(), 'test');

        $this->seoUrlRepository->create(
            [
                [
                    'id' => $canonicalId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'salesChannelId' => null,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/canonical',
                    'isCanonical' => true,
                ],
                [
                    'id' => $oldId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'salesChannelId' => null,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/old',
                    'isCanonical' => false,
                ],
            ],
            $salesChannelContext->getContext()
        );

        $criteria = new Criteria([$canonicalId, $oldId]);

        $seoUrls = $this->salesChannelSeoUrlRepository->search($criteria, $salesChannelContext);
        static::assertCount(1, $seoUrls);
        static::assertSame($canonicalId, $seoUrls->first()->getId());
    }

    public function testContextLanguage(): void
    {
        $deLanguageId = Uuid::randomHex();
        $deId = Uuid::randomHex();
        $enId = Uuid::randomHex();
        $foreignKey = Uuid::randomHex();

        $this->upsertLanguage($deLanguageId, 'test de');
        $salesChannelContext = $this->createStorefrontSalesChannelContext(Uuid::randomHex(), 'test', $deLanguageId);

        $this->seoUrlRepository->create(
            [
                [
                    'id' => $deId,
                    'languageId' => $deLanguageId,
                    'salesChannelId' => null,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/de',
                    'isCanonical' => true,
                ],
                [
                    'id' => $enId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'salesChannelId' => null,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/en',
                    'isCanonical' => true,
                ],
            ],
            $salesChannelContext->getContext()
        );

        $criteria = new Criteria([$deId, $enId]);

        $seoUrls = $this->salesChannelSeoUrlRepository->search($criteria, $salesChannelContext);
        static::assertCount(1, $seoUrls);
        static::assertSame($deId, $seoUrls->first()->getId());
    }

    public function testContextSalesChannel(): void
    {
        $expectedId = Uuid::randomHex();
        $otherId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $otherSalesChannelId = Uuid::randomHex();
        $foreignKey = Uuid::randomHex();

        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');
        $this->createStorefrontSalesChannelContext($otherSalesChannelId, 'other');

        $this->seoUrlRepository->create(
            [
                [
                    'id' => $expectedId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'salesChannelId' => $salesChannelId,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/expected',
                    'isCanonical' => true,
                ],
                [
                    'id' => $otherId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'salesChannelId' => $otherSalesChannelId,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/other',
                    'isCanonical' => true,
                ],
            ],
            $salesChannelContext->getContext()
        );

        $criteria = new Criteria([$expectedId, $otherId]);

        $seoUrls = $this->salesChannelSeoUrlRepository->search($criteria, $salesChannelContext);
        static::assertCount(1, $seoUrls);
        static::assertSame($expectedId, $seoUrls->first()->getId());
    }

    public function testSalesChannelFallback(): void
    {
        $expectedId = Uuid::randomHex();
        $otherId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $otherSalesChannelId = Uuid::randomHex();
        $foreignKey = Uuid::randomHex();

        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');
        $this->createStorefrontSalesChannelContext($otherSalesChannelId, 'other');

        $this->seoUrlRepository->create(
            [
                [
                    'id' => $expectedId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'salesChannelId' => null,
                    'routeName' => 'fallback',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/expected',
                    'isCanonical' => true,
                ],
                [
                    'id' => $otherId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'salesChannelId' => $otherSalesChannelId,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/other',
                    'isCanonical' => true,
                ],
            ],
            $salesChannelContext->getContext()
        );

        $criteria = new Criteria([$expectedId, $otherId]);

        $seoUrls = $this->salesChannelSeoUrlRepository->search($criteria, $salesChannelContext);
        static::assertCount(1, $seoUrls);
        static::assertSame($expectedId, $seoUrls->first()->getId());
    }

    public function testMatchingAndFallbackSalesChannel(): void
    {
        $expectedId = Uuid::randomHex();
        $expectedFallbackId = Uuid::randomHex();
        $otherId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $otherSalesChannelId = Uuid::randomHex();
        $foreignKey = Uuid::randomHex();

        $salesChannelContext = $this->createStorefrontSalesChannelContext($salesChannelId, 'test');
        $this->createStorefrontSalesChannelContext($otherSalesChannelId, 'other');

        $this->seoUrlRepository->create(
            [
                [
                    'id' => $expectedFallbackId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'salesChannelId' => null,
                    'routeName' => 'fallback',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/expected',
                    'isCanonical' => true,
                ],
                [
                    'id' => $expectedId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'salesChannelId' => $salesChannelId,
                    'routeName' => 'fallback',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/expected',
                    'isCanonical' => true,
                ],
                [
                    'id' => $otherId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'salesChannelId' => $otherSalesChannelId,
                    'routeName' => 'test',
                    'foreignKey' => $foreignKey,
                    'pathInfo' => '/detail/1234',
                    'seoPathInfo' => '/other',
                    'isCanonical' => true,
                ],
            ],
            $salesChannelContext->getContext()
        );

        $criteria = new Criteria([$expectedId, $expectedFallbackId, $otherId]);

        $seoUrls = $this->salesChannelSeoUrlRepository->search($criteria, $salesChannelContext);
        static::assertCount(2, $seoUrls);

        /** @var SeoUrlEntity|null $expected */
        $expected = $seoUrls->get($expectedId);
        static::assertNotNull($expected);
        static::assertSame($salesChannelId, $expected->getSalesChannelId());

        $expectedFallback = $seoUrls->get($expectedFallbackId);
        static::assertNotNull($expectedFallback);
        static::assertNull($expectedFallback->getSalesChannelId());
    }

    private function upsertLanguage(string $id, string $name): void
    {
        $languageRepo = $this->getContainer()->get('language.repository');
        $languageRepo->upsert([[
            'id' => $id,
            'name' => $name,
            'locale' => [
                'id' => $id,
                'code' => 'X-' . $name,
                'name' => 'test',
                'territory' => $name . ' territory',
            ],
            'translationCodeId' => $id,
        ]], Context::createDefaultContext());
    }
}
