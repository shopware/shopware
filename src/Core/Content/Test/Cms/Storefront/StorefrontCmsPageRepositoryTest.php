<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\Storefront;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\Storefront\StorefrontCmsPageRepository;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class StorefrontCmsPageRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var StorefrontCmsPageRepository
     */
    private $pageRepository;

    /**
     * @var CheckoutContext
     */
    private $checkoutContext;

    /**
     * @var EntityRepositoryInterface
     */
    private $cmsPageRepository;

    protected function setUp(): void
    {
        $contextFactory = $this->getContainer()->get(CheckoutContextFactory::class);

        $this->pageRepository = $this->getContainer()->get(StorefrontCmsPageRepository::class);
        $this->cmsPageRepository = $this->getContainer()->get('cms_page.repository');
        $this->checkoutContext = $contextFactory->create(Uuid::uuid4()->getHex(), Defaults::SALES_CHANNEL);
    }

    public function testWithEmptyIds(): void
    {
        $pageCollection = $this->pageRepository->read([], $this->checkoutContext);

        static::assertCount(0, $pageCollection);
    }

    public function testLoadPageWithAssociations(): void
    {
        $pageId = $this->createPage();

        $pageCollection = $this->pageRepository->read([$pageId], $this->checkoutContext);

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
        $faker = \Faker\Factory::create();

        $page = [
            'id' => Uuid::uuid4()->getHex(),
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

        $this->cmsPageRepository->create([$page], $this->checkoutContext->getContext());

        return $page['id'];
    }
}
