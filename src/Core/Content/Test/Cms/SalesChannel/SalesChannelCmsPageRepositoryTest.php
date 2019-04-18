<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\SalesChannel;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageRepository;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfig;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class SalesChannelCmsPageRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var SalesChannelCmsPageRepository
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

        $this->pageRepository = $this->getContainer()->get(SalesChannelCmsPageRepository::class);
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

    public function testSlotConfigStructureWithMissingProperties(): void
    {
        $page = [
            'id' => Uuid::randomHex(),
            'name' => 'foo',
            'type' => 'landing_page',
            'blocks' => [
                [
                    'position' => 1,
                    'type' => 'image-text',
                    'slots' => [
                        ['type' => 'text', 'slot' => 'left', 'config' => ['content' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => 'foo']]],
                        ['type' => 'image', 'slot' => 'right', 'config' => ['url' => ['value' => 'http://shopware.com/image.jpg']]],
                    ],
                ],
                [
                    'position' => 2,
                    'type' => 'image-text',
                    'slots' => [
                        ['type' => 'text', 'slot' => 'left', 'config' => ['content' => ['source' => FieldConfig::SOURCE_STATIC]]],
                        ['type' => 'image', 'slot' => 'right', 'config' => ['url' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => 'http://shopware.com/image.jpg']]],
                    ],
                ],
            ],
        ];

        $exception = null;
        try {
            $this->cmsPageRepository->create([$page], $this->salesChannelContext->getContext());
        } catch (WriteStackException $exception) {
        }

        static::assertInstanceOf(WriteStackException::class, $exception);
        static::assertCount(2, $exception->getExceptions());

        static::assertEquals('/blocks/0/slots/1/translations/' . Defaults::LANGUAGE_SYSTEM . '/config/url/source', $exception->getExceptions()[0]->getPath());
        static::assertEquals('/blocks/1/slots/0/translations/' . Defaults::LANGUAGE_SYSTEM . '/config/content/value', $exception->getExceptions()[1]->getPath());
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
                        ['type' => 'text', 'slot' => 'left', 'config' => ['content' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => $faker->realText()]]],
                        ['type' => 'image', 'slot' => 'right', 'config' => ['url' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => 'http://shopware.com/image.jpg']]],
                    ],
                ],
            ],
        ];

        $this->cmsPageRepository->create([$page], $this->salesChannelContext->getContext());

        return $page['id'];
    }
}
