<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Seo;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

class SeoUrlPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var EntityRepositoryInterface
     */
    private $seoUrlRepository;

    /**
     * @var SeoUrlPersister
     */
    private $seoUrlPersister;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var SeoUrlGenerator
     */
    private $seoUrlGenerator;

    public function setUp(): void
    {
        $this->seoUrlRepository = $this->getContainer()->get('seo_url.repository');
        $this->seoUrlPersister = $this->getContainer()->get(SeoUrlPersister::class);
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->seoUrlGenerator = $this->getContainer()->get(SeoUrlGenerator::class);

        $connection = $this->getContainer()->get(Connection::class);
        $connection->exec('DELETE FROM `sales_channel`');
        $connection->exec('DELETE FROM `seo_url`');
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
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertCount(1, $seoUrls);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path-2',
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(2, $seoUrls);

        $canonicalUrls = $seoUrls->filterByProperty('isCanonical', true);
        static::assertCount(1, $canonicalUrls);
        /** @var SeoUrlEntity $first */
        $first = $canonicalUrls->first();
        static::assertSame('fancy-path-2', $first->getSeoPathInfo());

        $obsoletedSeoUrls = $seoUrls->filterByProperty('isCanonical', null);

        static::assertCount(1, $obsoletedSeoUrls);
        /** @var SeoUrlEntity $first */
        $first = $obsoletedSeoUrls->first();
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
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $seoUrlUpdates);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('foreignKey', [$fk1, $fk2]));
        /** @var SeoUrlCollection $result */
        $result = $this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(1, $result);
        static::assertSame($fk2, $result->first()->getForeignKey());
    }

    /**
     * @depends testDuplicatesSameSalesChannel
     */
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
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $initialSeoUrlUpdates);

        $intermediateSeoUrlUpdates = [
            [
                'salesChannelId' => $salesChannelId,
                'foreignKey' => $fk1,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'intermediate',
            ],
        ];
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $intermediateSeoUrlUpdates);
        $this->seoUrlPersister->updateSeoUrls(Context::createDefaultContext(), 'r', $fks, $initialSeoUrlUpdates);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('foreignKey', [$fk1]));
        /** @var SeoUrlCollection $result */
        $result = $this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities();
        static::assertCount(2, $result);

        $canonicals = $result->filterByProperty('isCanonical', true);
        static::assertCount(1, $canonicals);

        /** @var SeoUrlEntity $canonical */
        $canonical = $canonicals->first();

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
        $this->seoUrlPersister->updateSeoUrls($defaultContext, 'r', $fks, $seoUrlUpdates);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($deContext, 'r', $fks, $seoUrlUpdates);

        $criteria = (new Criteria())->addFilter(new EqualsFilter('routeName', 'r'));
        /** @var SeoUrlCollection $result */
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
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'salesChannelId' => $salesChannelAId,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates);

        $seoUrlUpdates = [
            [
                'foreignKey' => $fk,
                'salesChannelId' => $salesChannelBId,
                'pathInfo' => 'normal/path',
                'seoPathInfo' => 'fancy-path',
            ],
        ];
        $fks = array_column($seoUrlUpdates, 'foreignKey');
        $this->seoUrlPersister->updateSeoUrls($context, 'r', $fks, $seoUrlUpdates);

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
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
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
        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
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

        $this->seoUrlPersister->updateSeoUrls($context, 'foo.route', array_column($seoUrlUpdates, 'foreignKey'), $seoUrlUpdates);
        $seoUrls = $this->seoUrlRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(2, $seoUrls);
        $canon = $seoUrls->filterByProperty('isCanonical', true)->first();
        static::assertNotNull($canon);

        static::assertTrue($canon->getIsModified());
        static::assertNotEquals('no-effect', $canon->getSeoPathInfo());
    }

    public function testUpdateSeoUrlsShouldMarkSeoUrlAsDeleted(): void
    {
        $isActive = false;
        $category = $this->createCategory($isActive);
        $this->createSeoUrlInDatabase($category->getId());

        $seoUrls = $this->generateSeoUrls($category->getId());

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            'frontend.navigation.page',
            [$category->getId()],
            $seoUrls
        );

        $seoUrl = $this->getSeoUrlFromDatabase($category->getId());

        static::assertTrue($seoUrl->getIsDeleted());
    }

    /**
     * @group slow
     */
    public function testUpdateSeoUrlsShouldMarkSeoUrlAsNotDeleted(): void
    {
        $isActive = true;
        $category = $this->createCategory($isActive);
        $this->createSeoUrlInDatabase($category->getId());

        $seoUrls = $this->generateSeoUrls($category->getId());

        $this->seoUrlPersister->updateSeoUrls(
            Context::createDefaultContext(),
            'frontend.navigation.page',
            [$category->getId()],
            $seoUrls
        );

        $seoUrl = $this->getSeoUrlFromDatabase($category->getId());

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

        return $this->categoryRepository->search(new Criteria([$id]), Context::createDefaultContext())->first();
    }

    private function getSeoUrlFromDatabase(string $categoryId): SeoUrlEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('foreignKey', $categoryId));

        return $this->seoUrlRepository->search($criteria, Context::createDefaultContext())->first();
    }

    private function createSeoUrlInDatabase(string $categoryId): void
    {
        $this->seoUrlRepository->create([
            [
                'foreignKey' => $categoryId,
                'routeName' => 'frontend.navigation.page',
                'pathInfo' => sprintf('navigation/%s', $categoryId),
                'seoPathInfo' => 'FancyCategory',
                'isCanonical' => true,
                'isDeleted' => false,
            ],
        ], Context::createDefaultContext());
    }

    private function generateSeoUrls(string $categoryId): iterable
    {
        return $this->seoUrlGenerator->generate(
            [$categoryId],
            'mytemplate',
            $this->getContainer()->get(NavigationPageSeoUrlRoute::class),
            Context::createDefaultContext(),
            null
        );
    }
}
