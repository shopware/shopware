<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Test\TestNavigationSeoUrlRoute;
use Shopware\Core\Defaults;
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

/**
 * @internal
 */
class SeoUrlPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    private SeoUrlPersister $seoUrlPersister;

    private EntityRepository $categoryRepository;

    private SeoUrlGenerator $seoUrlGenerator;

    private SalesChannelEntity $salesChannel;

    /**
     * @var EntityRepository<SalesChannelCollection>
     */
    private EntityRepository $salesChannelRepository;

    protected function setUp(): void
    {
        $this->seoUrlRepository = $this->getContainer()->get('seo_url.repository');
        $this->seoUrlPersister = $this->getContainer()->get(SeoUrlPersister::class);
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->seoUrlGenerator = $this->getContainer()->get(SeoUrlGenerator::class);
        $this->salesChannelRepository = $this->getContainer()->get('sales_channel.repository');

        $connection = $this->getContainer()->get(Connection::class);
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
        static::assertEquals($fk1, $canonical->getForeignKey());
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
        static::assertNotEquals('no-effect', $canon->getSeoPathInfo());
    }

    public function testUpdateSeoUrlsShouldMarkSeoUrlAsDeleted(): void
    {
        $category = $this->createCategory(false);
        $this->createSeoUrlInDatabase($category->getId(), $this->salesChannel->getId());

        $seoUrls = $this->generateSeoUrls($category->getId());

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

        $seoUrls = $this->generateSeoUrls($category->getId());

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

    /**
     * @return iterable<SeoUrlEntity>
     */
    private function generateSeoUrls(string $categoryId): iterable
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

        $navigation = $this->getContainer()->get(TestNavigationSeoUrlRoute::class);
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
