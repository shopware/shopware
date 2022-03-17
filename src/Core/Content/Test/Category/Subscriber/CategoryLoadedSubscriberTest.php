<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;

/** @internal */
class CategoryLoadedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SystemConfigService $systemConfigService;

    private EntityRepository $categoryRepository;

    private EntityRepository $cmsPageRepository;

    private SalesChannelRepository $salesChannelCategoryRepository;

    private SalesChannelContext $salesChannelContext;

    public function setUp(): void
    {
        $this->systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->cmsPageRepository = $this->getContainer()->get('cms_page.repository');
        $this->salesChannelCategoryRepository = $this->getContainer()->get('sales_channel.category.repository');
        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    /**
     * @dataProvider loadCategoryDataProvider
     */
    public function testLoadCategory(IdsCollection $ids, string $expectedCmsPageId, ?string $categoryCmsPageId = null): void
    {
        $cmsPageType = 'product_list';
        $this->createCmsPage($ids->get('overall-default'), $cmsPageType);
        $this->createCmsPage($ids->get('different-cms-page'), $cmsPageType);

        $this->systemConfigService->set(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY, $ids->get('overall-default'));

        $categoryId = $this->createCategory($categoryCmsPageId);

        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search(new Criteria([$categoryId]), Context::createDefaultContext())->getEntities()->first();

        static::assertEquals($expectedCmsPageId, $category->getCmsPageId());
    }

    /**
     * @dataProvider loadSalesChannelCategoryDataProvider
     */
    public function testLoadSalesChannelCategory(IdsCollection $ids, string $expectedCmsPageId, ?string $salesChannelDefault = null, ?string $categoryCmsPageId = null): void
    {
        $cmsPageType = 'product_list';
        $this->createCmsPage($ids->get('overall-default'), $cmsPageType);
        $this->createCmsPage($ids->get('different-cms-page'), $cmsPageType);

        if ($salesChannelDefault) {
            $this->createCmsPage($salesChannelDefault, $cmsPageType);
        }

        $this->systemConfigService->set(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY, $ids->get('overall-default'));
        $this->systemConfigService->set(CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY, $salesChannelDefault, TestDefaults::SALES_CHANNEL);

        $categoryId = $this->createCategory($categoryCmsPageId);

        /** @var CategoryEntity $salesChannelCategory */
        $salesChannelCategory = $this->salesChannelCategoryRepository->search(new Criteria([$categoryId]), $this->salesChannelContext)->getEntities()->first();

        static::assertEquals($expectedCmsPageId, $salesChannelCategory->getCmsPageId());
    }

    public function loadCategoryDataProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'It uses default if none is given' => [
            $ids,
            $ids->get('overall-default'),
        ];

        yield 'It does not set cms page id if already given' => [
            $ids,
            $ids->get('different-cms-page'),
            $ids->get('different-cms-page'),
        ];
    }

    public function loadSalesChannelCategoryDataProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'It uses salesChannel default if none is given' => [
            $ids,
            $ids->get('sales-channel-default'),
            $ids->get('sales-channel-default'),
        ];

        yield 'It uses overall default if no salesChannel default is given' => [
            $ids,
            $ids->get('overall-default'),
            null,
        ];

        yield 'It does not set cms page id if already given' => [
            $ids,
            $ids->get('different-cms-page'),
            $ids->get('sales-channel-default'),
            $ids->get('different-cms-page'),
        ];
    }

    private function createCmsPage(string $cmsPageId, string $type): void
    {
        $cmsPage = [
            'id' => $cmsPageId,
            'name' => 'test page',
            'type' => $type,
        ];

        $this->cmsPageRepository->create([$cmsPage], Context::createDefaultContext());
    }

    private function createCategory(?string $cmsPageId = null): string
    {
        $categoryId = Uuid::randomHex();

        $category = [
            'id' => $categoryId,
            'name' => 'category',
            'active' => true,
        ];

        if ($cmsPageId) {
            $category['cmsPageId'] = $cmsPageId;
        }

        $this->categoryRepository->create([$category], Context::createDefaultContext());

        return $categoryId;
    }
}
