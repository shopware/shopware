<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\SalesChannel;

use Faker\Factory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageRepository;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
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

    public function testLoadPageWithAssociations(): void
    {
        $pageId = $this->createPage();

        $pageCollection = $this->pageRepository->read([$pageId], $this->salesChannelContext);

        static::assertGreaterThanOrEqual(1, $pageCollection->count());

        /** @var CmsPageEntity $page */
        $page = $pageCollection->first();

        static::assertCount(2, $page->getSections());

        /** @var CmsSectionEntity $section */
        $section = $page->getSections()->first();

        static::assertCount(1, $section->getBlocks());
        static::assertEquals(1, $section->getPosition());

        /** @var CmsBlockEntity $block */
        $block = $section->getBlocks()->first();

        static::assertCount(2, $block->getSlots());
    }

    public function testSlotConfigStructureWithMissingProperties(): void
    {
        $page = [
            'id' => Uuid::randomHex(),
            'name' => 'foo',
            'type' => 'landing_page',
            'sections' => [
                [
                    'id' => Uuid::randomHex(),
                    'type' => 'default',
                    'position' => 0,
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
                ],
            ],
        ];

        try {
            $this->cmsPageRepository->create([$page], $this->salesChannelContext->getContext());
        } catch (WriteException $writeException) {
        }

        $errors = [];
        foreach ($writeException->getErrors() as $error) {
            $errors[] = $error;
        }

        static::assertEquals(
            '/0/sections/0/blocks/0/slots/1/translations/' . Defaults::LANGUAGE_SYSTEM . '/config/url/source',
            $errors[0]['source']['pointer']
        );

        static::assertEquals(
            '/0/sections/0/blocks/1/slots/0/translations/' . Defaults::LANGUAGE_SYSTEM . '/config/content/value',
            $errors[1]['source']['pointer']
        );
    }

    private function createPage(): string
    {
        $faker = Factory::create();

        $page = [
            'id' => Uuid::randomHex(),
            'name' => $faker->company,
            'type' => 'landing_page',
            'sections' => [
                [
                    'id' => Uuid::randomHex(),
                    'type' => 'default',
                    'position' => 2,
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
                ],
                [
                    'id' => Uuid::randomHex(),
                    'type' => 'default',
                    'position' => 1,
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
                ],
            ],
        ];

        $this->cmsPageRepository->create([$page], $this->salesChannelContext->getContext());

        return $page['id'];
    }
}
