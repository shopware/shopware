<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Content\Test\TestNavigationSeoUrlRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

/**
 * @internal
 */
class SeoUrlPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    private const LANGUAGE_IDS = [
        'en' => '1a2b3c4d5e6f708090a1b2c3d4e5f607',
        'de' => '2b3c4d5e6f708090a1b2c3d4e5f60708',
    ];

    private IdsCollection $ids;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    private SeoUrlPersister $seoUrlPersister;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    private SeoUrlGenerator $seoUrlGenerator;

    private SalesChannelEntity $salesChannel;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    protected function setUp(): void
    {
        $this->seoUrlRepository = static::getContainer()->get('seo_url.repository');
        $this->seoUrlPersister = static::getContainer()->get(SeoUrlPersister::class);
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->seoUrlGenerator = static::getContainer()->get(SeoUrlGenerator::class);
        $this->salesChannelRepository = static::getContainer()->get('sales_channel.repository');

        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM `sales_channel`');
        $connection->executeStatement('DELETE FROM `seo_url`');

        $id = $this->createSalesChannel()['id'];
        $salesChannels = $this->salesChannelRepository
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->getEntities();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannels->first());
        $this->salesChannel = $salesChannels->first();
    }

    public function testUpdateSeoUrlsDefault(): void
    {
        $context = Context::createDefaultContext();

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->salesChannel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->salesChannel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path-2',
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->salesChannel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(2, $seoUrls);

        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        static::assertCount(1, $canonicalUrls);

        $first = $canonicalUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
        static::assertSame('fancy-path-2', $first->getSeoPathInfo());

        $obsoletedSeoUrls = $seoUrls->filterByProperty('isCanonical', null);

        static::assertCount(1, $obsoletedSeoUrls);

        $first = $obsoletedSeoUrls->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
        static::assertSame('fancy-path', $first->getSeoPathInfo());
    }

    public function testDuplicatesSameSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $fk1 = Uuid::randomHex();
        $fk2 = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'salesChannelId' => $salesChannelId,
                'foreignKey' => $fk1,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
            [
                'salesChannelId' => $salesChannelId,
                'foreignKey' => $fk2,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $seoUrlUpdates, $this->salesChannel);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('foreignKey', [$fk1, $fk2]));

        $result = $this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(1, $result);
        $first = $result->first();
        static::assertInstanceOf(SeoUrlEntity::class, $first);
        static::assertSame($fk2, $first->getForeignKey());
    }

    #[Depends('testDuplicatesSameSalesChannel')]
    public function testReturnToPreviousUrl(): void
    {
        $salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelId, 'test');

        $fk1 = Uuid::randomHex();
        $initialSeoUrlUpdates = [
            [
                'salesChannelId' => $salesChannelId,
                'foreignKey' => $fk1,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($initialSeoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $initialSeoUrlUpdates, $this->salesChannel);

        $intermediateSeoUrlUpdates = [
            [
                'salesChannelId' => $salesChannelId,
                'foreignKey' => $fk1,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'intermediate',
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $intermediateSeoUrlUpdates, $this->salesChannel);
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $initialSeoUrlUpdates, $this->salesChannel);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('foreignKey', [$fk1]));

        $result = $this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertCount(2, $result);

        $canonicals = $result->filterByProperty('isCanonical', true);
        static::assertCount(1, $canonicals);

        $canonical = $canonicals->first();
        static::assertInstanceOf(SeoUrlEntity::class, $canonical);
        static::assertSame($fk1, $canonical->getForeignKey());
    }

    public function testSameSeoPathDifferentLanguage(): void
    {
        $defaultContext = Context::createDefaultContext();
        $deContext = new Context($defaultContext->getSource(), [], $defaultContext->getCurrencyId(), [$this->getDeDeLanguageId()]);

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($defaultContext, 'r', $fks, $seoUrlUpdates, $this->salesChannel);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($deContext, 'r', $fks, $seoUrlUpdates, $this->salesChannel);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('routeName', 'r'));

        $result = $this->seoUrlRepository->search($criteria, $defaultContext)->getEntities();
        static::assertCount(2, $result);
    }

    public function testSameSeoPathInfoDifferentSalesChannels(): void
    {
        $context = Context::createDefaultContext();

        $salesChannelAId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelAId, 'test a');

        $salesChannelBId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($salesChannelBId, 'test b');

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates, $this->salesChannel);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'salesChannelId' => $salesChannelAId,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates, $this->salesChannel);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'salesChannelId' => $salesChannelBId,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates, $this->salesChannel);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('routeName', 'r'));
        /** @var SeoUrlCollection $result */
        $result = $this->seoUrlRepository->search($criteria, $context)->getEntities();
        static::assertCount(3, $result);
    }

    public function testUpdateDefaultIsModified(): void
    {
        $context = Context::createDefaultContext();

        $fk = Uuid::randomHex();
        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'default',
                'isModified' => false,
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->salesChannel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-override',
                'isModified' => true,
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->salesChannel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(2, $seoUrls);
        $canon = $seoUrls->filterByProperty('isCanonical', true)->first();
        static::assertNotNull($canon);

        static::assertTrue($canon->getIsModified());
        static::assertSame('fancy-override', $canon->getSeoPathInfo());

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'no-effect',
            ],
        ];

        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates, $this->salesChannel);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(2, $seoUrls);
        $canon = $seoUrls->filterByProperty('isCanonical', true)->first();
        static::assertNotNull($canon);

        static::assertTrue($canon->getIsModified());
        static::assertNotSame('no-effect', $canon->getSeoPathInfo());
    }

    public function testUpdateSeoUrlsShouldMarkSeoUrlAsDeleted(): void
    {
        $category = $this->createCategory(false);
        $this->createSeoUrlInDatabase($category->getId(), $this->salesChannel->getId());

        $seoUrls = $this->generateCategorySeoUrls($category->getId());

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            TestNavigationSeoUrlRoute::ROUTE_NAME,
            [$category->getId()],
            $seoUrls,
            $this->salesChannel
        );

        $seoUrl = $this->getSeoUrlFromDatabase($category->getId());

        static::assertNotNull($seoUrl);
        static::assertTrue($seoUrl->getIsDeleted());
    }

    #[Group('slow')]
    public function testUpdateSeoUrlsShouldMarkSeoUrlAsNotDeleted(): void
    {
        $isActive = true;
        $category = $this->createCategory($isActive);
        $this->createSeoUrlInDatabase($category->getId(), $this->salesChannel->getId());

        $seoUrls = $this->generateCategorySeoUrls($category->getId());

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            'frontend.navigation.page',
            [$category->getId()],
            $seoUrls,
            $this->salesChannel
        );

        $seoUrl = $this->getSeoUrlFromDatabase($category->getId());

        static::assertNotNull($seoUrl);
        static::assertFalse($seoUrl->getIsDeleted());
    }

    public function testUpdaterDoesNotTouchOtherUrlsFromOtherSalesChannels(): void
    {
        $category = $this->createCategory(true);

        $this->seoUrlRepository->create([
            [
                'foreignKey' => $category->getId(),
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => \sprintf('navigation/%s', $category->getId()),
                'seoPathInfo' => 'FancyCategory',
                'isCanonical' => true,
                'isDeleted' => false,
                'salesChannelId' => $this->salesChannel->getId(),
            ],
        ], Context::createDefaultContext());

        $otherSalesChannelId = $this->createSalesChannel([
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://second',
                ],
            ],
        ])['id'];

        $otherSalesChannels = $this->salesChannelRepository
            ->search(new Criteria([$otherSalesChannelId]), Context::createDefaultContext())
            ->getEntities();
        static::assertInstanceOf(SalesChannelEntity::class, $otherSalesChannels->first());

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            'frontend.navigation.page',
            [$category->getId()],
            [],
            $otherSalesChannels->first()
        );

        $seoUrl = $this->getSeoUrlFromDatabase($category->getId());

        static::assertNotNull($seoUrl);
        static::assertFalse($seoUrl->getIsDeleted());
    }

    public function testUpdateSeoUrlForDifferentSalesChannelsWithSameSeoPathInfo(): void
    {
        $context = Context::createDefaultContext();

        $salesChannels = [
            ['id' => Uuid::randomHex(), 'name' => 'test a'],
            ['id' => Uuid::randomHex(), 'name' => 'test b'],
        ];
        foreach ($salesChannels as $sc) {
            $this->createStorefrontSalesChannelContext($sc['id'], $sc['name']);
        }

        $fk = Uuid::randomHex();
        $seoPaths = ['fancy-path', 'fancy-path-2'];

        foreach ($seoPaths as $seoPath) {
            foreach ($salesChannels as $sc) {
                $seoUrlUpdates = [[
                    'foreignKey' => $fk,
                    'salesChannelId' => $sc['id'],
                    'pathInfo' => 'normal/path',
                    'seoPathInfo' => $seoPath,
                    'isCanonical' => true,
                ]];
                $fks = array_column($seoUrlUpdates, 'foreignKey');
                $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates, $this->salesChannel);
            }
        }

        $criteria = (new Criteria())->addFilter(new EqualsFilter('routeName', 'r'));
        $result = $this->seoUrlRepository->search($criteria, $context)->getEntities();
        static::assertInstanceOf(SeoUrlCollection::class, $result);
        static::assertCount(4, $result);

        $canonicalUrls = $result->filterByProperty('isCanonical', true);
        static::assertCount(2, $canonicalUrls);

        foreach ($canonicalUrls as $url) {
            static::assertSame('fancy-path-2', $url->getSeoPathInfo());
            static::assertContains($url->getSalesChannelId(), array_column($salesChannels, 'id'));
        }

        $notCanonicalUrls = $result->filterByProperty('isCanonical', null);
        static::assertCount(2, $notCanonicalUrls);

        foreach ($notCanonicalUrls as $url) {
            static::assertSame('fancy-path', $url->getSeoPathInfo());
            static::assertContains($url->getSalesChannelId(), array_column($salesChannels, 'id'));
        }
    }

    public function testMultilingualIsolationCase(): void
    {
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');
        /** @var EntityRepository<SeoUrlCollection> $seoUrlRepository */
        $seoUrlRepository = static::getContainer()->get('seo_url.repository');

        $context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $this->createLanguages($context);
        $languageIds = [self::LANGUAGE_IDS['en'], self::LANGUAGE_IDS['de']];

        $product = (new ProductBuilder($this->ids, 'test'))
            ->name('test a')
            ->price(69)
            ->visibility($this->salesChannel->getId())
            ->build();
        $productRepository->create([$product], $context);

        $seoUrlTemplate = '{{ product.translated.name }}/{{ product.productNumber }}';

        foreach ($languageIds as $languageId) {
            $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]);
            $languageContext->setConsiderInheritance(true);

            $productRepository->update([[
                'id' => $product['id'],
                'name' => 'test a',
            ]], $languageContext);

            // Generate SEO URLs with correct context and sales channel
            $seoUrls = $this->seoUrlGenerator->generate(
                [$product['id']],
                $seoUrlTemplate,
                static::getContainer()->get(ProductPageSeoUrlRoute::class),
                $languageContext,
                $this->salesChannel
            );

            $this->seoUrlPersister->updateSeoUrls(
                $languageContext,
                ProductPageSeoUrlRoute::ROUTE_NAME,
                [$product['id']],
                $seoUrls,
                $this->salesChannel
            );
        }

        $seoUrlsAfterFirstLoop = $this->getAllSeoUrlsForProduct($product['id'], $seoUrlRepository);
        static::assertCount(\count($languageIds), $seoUrlsAfterFirstLoop, 'SEO URL should be created for each language');

        foreach ($seoUrlsAfterFirstLoop as $seoUrl) {
            static::assertStringContainsString('test-a', $seoUrl['seoPathInfo'], 'SEO URL should contain "test-a" from first loop');
            static::assertTrue($seoUrl['isCanonical'], 'SEO URLs should be canonical after first loop');
        }

        foreach ($languageIds as $languageId) {
            $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]);
            $languageContext->setConsiderInheritance(true);

            $productRepository->update([[
                'id' => $product['id'],
                'name' => 'test b',
            ]], $languageContext);

            // Generate SEO URLs with correct context and sales channel
            $seoUrls = $this->seoUrlGenerator->generate(
                [$product['id']],
                $seoUrlTemplate,
                static::getContainer()->get(ProductPageSeoUrlRoute::class),
                $languageContext,
                $this->salesChannel
            );

            $this->seoUrlPersister->updateSeoUrls(
                $languageContext,
                ProductPageSeoUrlRoute::ROUTE_NAME,
                [$product['id']],
                $seoUrls,
                $this->salesChannel
            );
        }

        $currentCanonicalUrls = $this->getCurrentCanonicalSeoUrls($product['id'], $seoUrlRepository);
        static::assertCount(\count($languageIds), $currentCanonicalUrls, 'New canonical SEO URLs should be created for each language');

        foreach ($currentCanonicalUrls as $seoUrl) {
            static::assertStringContainsString('test-b', $seoUrl['seoPathInfo'], 'New SEO URL should contain "test-b" from second loop');
            static::assertTrue($seoUrl['isCanonical'], 'New SEO URLs should be canonical');
        }

        $obsoleteUrls = $this->getObsoleteSeoUrls($product['id'], $seoUrlRepository);
        static::assertGreaterThanOrEqual(\count($languageIds), \count($obsoleteUrls), 'Old "test a" URLs should be marked as obsolete');

        foreach ($obsoleteUrls as $obsoleteUrl) {
            static::assertStringContainsString('test-a', $obsoleteUrl['seoPathInfo'], 'Obsolete URLs should be the old "test-a" ones');
            static::assertNull($obsoleteUrl['isCanonical'], 'Obsolete URLs should not be canonical');
        }

        $languageRepository = static::getContainer()->get('language.repository');
        $languageRepository->delete([
            ['id' => self::LANGUAGE_IDS['en']],
            ['id' => self::LANGUAGE_IDS['de']],
        ], $context);
    }

    public function testSeoUrlConflictResolution(): void
    {
        /** @var EntityRepository<ProductCollection> $productRepository */
        $productRepository = static::getContainer()->get('product.repository');
        /** @var EntityRepository<SeoUrlCollection> $seoUrlRepository */
        $seoUrlRepository = static::getContainer()->get('seo_url.repository');

        $context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $this->createLanguages($context);
        $languageId = self::LANGUAGE_IDS['en'];

        $product1 = (new ProductBuilder($this->ids, 'product1'))
            ->name('Awesome Product')
            ->price(99)
            ->visibility($this->salesChannel->getId())
            ->build();

        $product2 = (new ProductBuilder($this->ids, 'product2'))
            ->name('Different Product')
            ->price(49)
            ->visibility($this->salesChannel->getId())
            ->build();

        $languageContext = new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]);
        $languageContext->setConsiderInheritance(true);

        $productRepository->create([$product1, $product2], $context);

        $productRepository->update([[
            'id' => $product1['id'],
            'name' => 'Awesome Product',
        ]], $languageContext);

        $productRepository->update([[
            'id' => $product2['id'],
            'name' => 'Different Product',
        ]], $languageContext);

        $seoUrlTemplate = '{{ product.translated.name }}/test';

        $seoUrls = $this->seoUrlGenerator->generate(
            [$product1['id']],
            $seoUrlTemplate,
            static::getContainer()->get(ProductPageSeoUrlRoute::class),
            $languageContext,
            $this->salesChannel
        );

        $this->seoUrlPersister->updateSeoUrls(
            $languageContext,
            ProductPageSeoUrlRoute::ROUTE_NAME,
            [$product1['id']],
            $seoUrls,
            $this->salesChannel
        );

        $productRepository->update([[
            'id' => $product1['id'],
            'name' => 'Awesome Product Updated', // Creates new canonical, old becomes obsolete
        ]], $languageContext);

        $updatedSeoUrls = $this->seoUrlGenerator->generate(
            [$product1['id']],
            $seoUrlTemplate,
            static::getContainer()->get(ProductPageSeoUrlRoute::class),
            $languageContext,
            $this->salesChannel
        );

        $this->seoUrlPersister->updateSeoUrls(
            $languageContext,
            ProductPageSeoUrlRoute::ROUTE_NAME,
            [$product1['id']],
            $updatedSeoUrls,
            $this->salesChannel
        );

        $product1SeoUrls = $this->getAllSeoUrlsForProduct($product1['id'], $seoUrlRepository);
        static::assertCount(2, $product1SeoUrls, 'Product 1 should have 2 SEO URLs (canonical + obsolete)');

        $product1CanonicalUrl = null;
        $product1ObsoleteUrl = null;
        foreach ($product1SeoUrls as $seoUrl) {
            if ($seoUrl['isCanonical']) {
                $product1CanonicalUrl = $seoUrl;
            } else {
                $product1ObsoleteUrl = $seoUrl;
            }
        }

        static::assertNotNull($product1CanonicalUrl, 'Product 1 should have a canonical URL');
        static::assertNotNull($product1ObsoleteUrl, 'Product 1 should have an obsolete URL');
        static::assertStringContainsString('Awesome-Product-Updated', $product1CanonicalUrl['seoPathInfo']);
        static::assertStringContainsString('Awesome-Product', $product1ObsoleteUrl['seoPathInfo']);

        $productRepository->update([[
            'id' => $product2['id'],
            'name' => 'Awesome Product Updated', // Same name = same SEO path
        ]], $languageContext);

        $product2SeoUrls = $this->seoUrlGenerator->generate(
            [$product2['id']],
            $seoUrlTemplate,
            static::getContainer()->get(ProductPageSeoUrlRoute::class),
            $languageContext,
            $this->salesChannel
        );

        $this->seoUrlPersister->updateSeoUrls(
            $languageContext,
            ProductPageSeoUrlRoute::ROUTE_NAME,
            [$product2['id']],
            $product2SeoUrls,
            $this->salesChannel
        );

        $product1SeoUrlsAfterConflict = $this->getAllSeoUrlsForProduct($product1['id'], $seoUrlRepository);
        $product2SeoUrlsAfterConflict = $this->getAllSeoUrlsForProduct($product2['id'], $seoUrlRepository);

        $product1NewCanonical = null;
        $product1ObsoleteUrls = [];
        foreach ($product1SeoUrlsAfterConflict as $seoUrl) {
            if ($seoUrl['isCanonical']) {
                $product1NewCanonical = $seoUrl;
            } else {
                $product1ObsoleteUrls[] = $seoUrl;
            }
        }

        static::assertNotNull($product1NewCanonical, 'Product 1 should have a canonical URL');
        static::assertStringContainsString('Awesome-Product', $product1NewCanonical['seoPathInfo']);
        static::assertEmpty($product1ObsoleteUrls);

        $product2Canonical = null;
        foreach ($product2SeoUrlsAfterConflict as $seoUrl) {
            if ($seoUrl['isCanonical']) {
                $product2Canonical = $seoUrl;
            }
        }

        static::assertNotNull($product2Canonical, 'Product 2 should have a canonical URL');
        static::assertStringContainsString(
            'Awesome-Product-Updated',
            $product2Canonical['seoPathInfo'],
            'Product 2 should have taken over the conflicting path'
        );

        $languageRepository = static::getContainer()->get('language.repository');
        $languageRepository->delete([
            ['id' => self::LANGUAGE_IDS['en']],
            ['id' => self::LANGUAGE_IDS['de']],
        ], $context);
    }

    private function createLanguages(Context $context): void
    {
        $languages = [[
            'id' => self::LANGUAGE_IDS['en'],
            'name' => 'TestEnglish',
            'locale' => [
                'id' => $this->ids->create('locale-en'),
                'name' => 'TestEnglish',
                'territory' => 'TestEngland',
                'code' => 'en-GB-test',
            ],
            'active' => true,
            'translationCodeId' => $this->ids->get('locale-en'),
        ], [
            'id' => self::LANGUAGE_IDS['de'],
            'name' => 'TestGerman',
            'locale' => [
                'id' => $this->ids->create('locale-de'),
                'name' => 'TestGerman',
                'territory' => 'TestGermany',
                'code' => 'de-DE-test',
            ],
            'active' => true,
            'translationCodeId' => $this->ids->get('locale-de'),
        ]];

        $this->getContainer()->get('language.repository')->create($languages, $context);
    }

    /**
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     *
     * @return array<array{seoPathInfo: string, isCanonical: bool, isDeleted: bool, languageId: string}>
     */
    private function getAllSeoUrlsForProduct(string $productId, EntityRepository $seoUrlRepository): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $productId));
        $criteria->addFilter(new EqualsFilter('routeName', ProductPageSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsAnyFilter('languageId', self::LANGUAGE_IDS));

        $seoUrls = $seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);

        return $seoUrls->map(fn (SeoUrlEntity $url) => [
            'seoPathInfo' => $url->getSeoPathInfo(),
            'isCanonical' => $url->getIsCanonical(),
            'isDeleted' => $url->getIsDeleted(),
            'languageId' => $url->getLanguageId(),
        ]);
    }

    /**
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     *
     * @return array<array{seoPathInfo: string, isCanonical: bool, isDeleted: bool, languageId: string}>
     */
    private function getCurrentCanonicalSeoUrls(string $productId, EntityRepository $seoUrlRepository): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $productId));
        $criteria->addFilter(new EqualsFilter('routeName', ProductPageSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('isCanonical', true));
        $criteria->addFilter(new EqualsFilter('isDeleted', false));
        $criteria->addFilter(new EqualsAnyFilter('languageId', self::LANGUAGE_IDS));

        $seoUrls = $seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);

        return $seoUrls->map(fn (SeoUrlEntity $url) => [
            'seoPathInfo' => $url->getSeoPathInfo(),
            'isCanonical' => $url->getIsCanonical(),
            'isDeleted' => $url->getIsDeleted(),
            'languageId' => $url->getLanguageId(),
        ]);
    }

    /**
     * @param EntityRepository<SeoUrlCollection> $seoUrlRepository
     *
     * @return array<array{seoPathInfo: string, isCanonical: bool, isDeleted: bool, languageId: string}>
     */
    private function getObsoleteSeoUrls(string $productId, EntityRepository $seoUrlRepository): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $productId));
        $criteria->addFilter(new EqualsFilter('routeName', ProductPageSeoUrlRoute::ROUTE_NAME));
        $criteria->addFilter(new EqualsFilter('isCanonical', null));
        $criteria->addFilter(new EqualsAnyFilter('languageId', self::LANGUAGE_IDS));

        $seoUrls = $seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertInstanceOf(SeoUrlCollection::class, $seoUrls);

        return $seoUrls->map(fn (SeoUrlEntity $url) => [
            'seoPathInfo' => $url->getSeoPathInfo(),
            'isCanonical' => $url->getIsCanonical(),
            'isDeleted' => $url->getIsDeleted(),
            'languageId' => $url->getLanguageId(),
        ]);
    }

    private function createCategory(bool $active): CategoryEntity
    {
        $id = Uuid::randomHex();

        $this->categoryRepository->create([[
            'id' => $id,
            'active' => $active,
            'name' => 'FancyCategory',
        ]], Context::createDefaultContext());

        $first = $this->categoryRepository->search(new Criteria([$id]), Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(CategoryEntity::class, $first);

        return $first;
    }

    private function getSeoUrlFromDatabase(string $categoryId): ?SeoUrlEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $categoryId));

        return $this->seoUrlRepository->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();
    }

    private function createSeoUrlInDatabase(string $categoryId, string $salesChannelId): void
    {
        $this->seoUrlRepository->create([
            [
                'foreignKey' => $categoryId,
                'routeName' => TestNavigationSeoUrlRoute::ROUTE_NAME,
                'pathInfo' => \sprintf('test/%s', $categoryId),
                'salesChannelId' => $salesChannelId,
                'seoPathInfo' => 'FancyCategory',
                'isCanonical' => true,
                'isDeleted' => false,
            ],
        ], Context::createDefaultContext());
    }

    private function findRandomSalesChannel(): SalesChannelEntity
    {
        /** @var SalesChannelEntity|null $salesChannel */
        $salesChannel = $this->salesChannelRepository
            ->search(
                (new Criteria())->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT))->setLimit(1),
                Context::createDefaultContext()
            )
            ->first();

        if ($salesChannel === null) {
            static::markTestSkipped('Sales channel with type of storefront is required');
        }

        return $salesChannel;
    }

    /**
     * @return iterable<SeoUrlEntity>
     */
    private function generateCategorySeoUrls(string $categoryId): iterable
    {
        $salesChannel = $this->findRandomSalesChannel();

        $navigation = static::getContainer()->get(TestNavigationSeoUrlRoute::class);
        static::assertInstanceOf(SeoUrlRouteInterface::class, $navigation);

        return $this->seoUrlGenerator->generate(
            [$categoryId],
            'mytemplate',
            $navigation,
            Context::createDefaultContext(),
            $salesChannel
        );
    }
}
