<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;

class SalesChannelCmsPageLoaderTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var SalesChannelCmsPageLoaderInterface
     */
    private $salesChannelCmsPageLoader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->salesChannelCmsPageLoader = $this->getContainer()->get(SalesChannelCmsPageLoader::class);
    }

    public function testSlotOverwrite(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();

        $firstSlotId = Uuid::randomHex();
        $secondSlotId = Uuid::randomHex();

        $category = [
            'id' => Uuid::randomHex(),
            'name' => 'test category',
            'cmsPage' => [
                'id' => Uuid::randomHex(),
                'name' => 'test page',
                'type' => 'landingpage',
                'sections' => [
                    [
                        'id' => Uuid::randomHex(),
                        'type' => 'default',
                        'position' => 0,
                        'blocks' => [
                            [
                                'type' => 'text',
                                'position' => 0,
                                'slots' => [
                                    [
                                        'id' => $firstSlotId,
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => [
                                            'content' => [
                                                'source' => 'static',
                                                'value' => 'initial',
                                            ],
                                        ],
                                    ],
                                    [
                                        'id' => $secondSlotId,
                                        'type' => 'text',
                                        'slot' => 'content',
                                        'config' => null,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->categoryRepository->create([$category], $salesChannelContext->getContext());

        $pages = $this->salesChannelCmsPageLoader->load(
            new Request(),
            new Criteria([$category['cmsPage']['id']]),
            $salesChannelContext,
            []
        );

        static::assertEquals(1, $pages->getTotal());

        /** @var CmsPageEntity $page */
        $page = $pages->first();

        $fieldConfigCollection = new FieldConfigCollection([new FieldConfig('content', 'static', 'initial')]);

        static::assertEquals(
            $category['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['config'],
            $page->getSections()->first()->getBlocks()->getSlots()->get($firstSlotId)->getConfig()
        );

        static::assertEquals(
            $fieldConfigCollection,
            $page->getSections()->first()->getBlocks()->getSlots()->get($firstSlotId)->getFieldConfig()
        );

        static::assertNull(
            $page->getSections()->first()->getBlocks()->getSlots()->get($secondSlotId)->getConfig()
        );

        // overwrite in category
        $customSlotConfig = [
            $category['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['id'] => [
                'content' => [
                    'source' => 'static',
                    'value' => 'overwrite',
                ],
            ],
            $category['cmsPage']['sections'][0]['blocks'][0]['slots'][1]['id'] => [
                'content' => [
                    'source' => 'static',
                    'value' => 'overwrite',
                ],
            ],
        ];

        $pages = $this->salesChannelCmsPageLoader->load(
            new Request(),
            new Criteria([$category['cmsPage']['id']]),
            $salesChannelContext,
            $customSlotConfig
        );

        static::assertGreaterThanOrEqual(1, $pages->getTotal());

        /** @var CmsPageEntity $page */
        $page = $pages->first();

        $fieldConfigCollection = new FieldConfigCollection([new FieldConfig('content', 'static', 'overwrite')]);

        static::assertEquals(
            $customSlotConfig[$category['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['id']],
            $page->getSections()->first()->getBlocks()->getSlots()->get($firstSlotId)->getConfig()
        );

        static::assertEquals(
            $fieldConfigCollection,
            $page->getSections()->first()->getBlocks()->getSlots()->get($firstSlotId)->getFieldConfig()
        );

        static::assertEquals(
            $customSlotConfig[$category['cmsPage']['sections'][0]['blocks'][0]['slots'][1]['id']],
            $page->getSections()->first()->getBlocks()->getSlots()->get($secondSlotId)->getConfig()
        );
    }
}
