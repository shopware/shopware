<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Seo\SeoUrl;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlCollection;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\Seo\StorefrontSalesChannelTestHelper;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;

/**
 * @internal
 */
#[Package('inventory')]
class LinkCategorySeoUrlTest extends TestCase
{
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use StorefrontSalesChannelTestHelper;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<SeoUrlCollection>
     */
    private EntityRepository $seoUrlRepository;

    private string $salesChannelId;

    private string $rootId;

    protected function setUp(): void
    {
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->seoUrlRepository = static::getContainer()->get('seo_url.repository');

        $this->salesChannelId = Uuid::randomHex();
        $this->createStorefrontSalesChannelContext($this->salesChannelId, 'test');

        $this->rootId = Uuid::randomHex();
        $this->categoryRepository->create([['id' => $this->rootId, 'name' => 'root']], Context::createDefaultContext());
        $this->updateSalesChannelNavigationEntryPoint($this->salesChannelId, $this->rootId);
        $this->runWorker();
    }

    public function testLinkCategoryDoesNotTakeOverTheSeoUrlOfItsSameNamedTarget(): void
    {
        $pageId = $this->createCategory(['name' => 'Old guide']);
        $this->categoryRepository->update([['id' => $pageId, 'name' => 'Guide']], Context::createDefaultContext());
        $this->runWorker();
        static::assertSame(['Guide/'], $this->getCanonicalSeoPaths($pageId));

        $linkId = $this->createCategory([
            'name' => 'Guide',
            'type' => CategoryDefinition::TYPE_LINK,
            'linkType' => CategoryDefinition::LINK_TYPE_CATEGORY,
            'internalLink' => $pageId,
        ]);

        static::assertSame(['Guide/'], $this->getCanonicalSeoPaths($pageId));
        static::assertSame([], $this->getSeoPaths($linkId));

        static::getContainer()->get(SeoUrlUpdater::class)->update(NavigationPageSeoUrlRoute::ROUTE_NAME, [$pageId, $linkId]);

        static::assertSame(['Guide/'], $this->getCanonicalSeoPaths($pageId));
        static::assertSame([], $this->getSeoPaths($linkId));
    }

    public function testCategoryChangedFromPageToLinkKeepsItsSeoUrlAfterReindexing(): void
    {
        $categoryId = $this->createCategory(['name' => 'Old page']);
        static::assertSame(['Old-page/'], $this->getCanonicalSeoPaths($categoryId));

        $this->categoryRepository->update([[
            'id' => $categoryId,
            'type' => CategoryDefinition::TYPE_LINK,
            'linkType' => CategoryDefinition::LINK_TYPE_EXTERNAL,
            'externalLink' => 'https://example.com',
        ]], Context::createDefaultContext());
        $this->runWorker();

        static::getContainer()->get(SeoUrlUpdater::class)->update(NavigationPageSeoUrlRoute::ROUTE_NAME, [$categoryId]);

        static::assertSame(['Old-page/'], $this->getCanonicalSeoPaths($categoryId));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createCategory(array $data): string
    {
        $id = Uuid::randomHex();
        $this->categoryRepository->create([['id' => $id, 'parentId' => $this->rootId, ...$data]], Context::createDefaultContext());
        $this->runWorker();

        return $id;
    }

    /**
     * @return list<string>
     */
    private function getCanonicalSeoPaths(string $categoryId): array
    {
        return $this->getSeoPaths($categoryId, new EqualsFilter('isCanonical', true), new EqualsFilter('isDeleted', false));
    }

    /**
     * @return list<string>
     */
    private function getSeoPaths(string $categoryId, EqualsFilter ...$filters): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('foreignKey', $categoryId),
            new EqualsFilter('routeName', NavigationPageSeoUrlRoute::ROUTE_NAME),
            new EqualsFilter('salesChannelId', $this->salesChannelId),
            ...$filters
        );

        return array_values($this->seoUrlRepository->search($criteria, Context::createDefaultContext())->getEntities()->map(
            static fn ($seoUrl) => $seoUrl->getSeoPathInfo()
        ));
    }
}
