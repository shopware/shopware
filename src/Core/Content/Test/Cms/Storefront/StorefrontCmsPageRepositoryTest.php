<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\Storefront;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Storefront\StorefrontCmsPageRepository;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class StorefrontCmsPageRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var StorefrontCmsPageRepository
     */
    private $pageRepository;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    /**
     * @var EntityRepositoryInterface
     */
    private $cmsPageRepository;

    protected function setUp(): void
    {
        $contextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        $this->pageRepository = $this->getContainer()->get(StorefrontCmsPageRepository::class);
        $this->cmsPageRepository = $this->getContainer()->get('cms_page.repository');
        $this->salesChannelContext = $contextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    public function testWithEmptyIds(): void
    {
        $pageCollection = $this->pageRepository->read([], $this->salesChannelContext);

        static::assertCount(0, $pageCollection);
    }

    public function testLoadPageWithAssociations(): void
    {
        $pageId = $this->createPage();

        $pageCollection = $this->pageRepository->read([$pageId], $this->salesChannelContext);

        static::assertGreaterThanOrEqual(1, $pageCollection->count());

        /** @var CmsPageEntity $page */
        $page = $pageCollection->first();

        static::assertCount(1, $page->getBlocks());

        /** @var CmsBlockEntity $block */
        $block = $page->getBlocks()->first();

        static::assertCount(2, $block->getSlots());
    }

    private function createPage(): string
    {
        $faker = Factory::create();

        $page = [
            'id' => Uuid::randomHex(),
            'name' => $faker->company,
            'type' => 'landing_page',
            'blocks' => [
                [
                    'position' => 1,
                    'type' => 'image-text',
                    'slots' => [
                        ['type' => 'text', 'slot' => 'left', 'config' => ['content' => $faker->realText()]],
                        ['type' => 'image', 'slot' => 'right', 'config' => ['url' => 'http://shopware.com/image.jpg']],
                    ],
                ],
            ],
        ];

        $this->cmsPageRepository->create([$page], $this->salesChannelContext->getContext());

        return $page['id'];
    }
}
