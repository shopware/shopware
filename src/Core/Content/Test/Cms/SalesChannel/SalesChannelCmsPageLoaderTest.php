<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfig;
use Shopware\Core\Content\Cms\SlotDataResolver\FieldConfigCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Page\Navigation\NavigationPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

class SalesChannelCmsPageLoaderTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;
    use StorefrontPageTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var SalesChannelCmsPageLoader
     */
    private $salesChannelCmsPageLoader;

    /**
     * @var NavigationPageLoader
     */
    private $navigationPageLoader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->navigationPageLoader = $this->getContainer()->get(NavigationPageLoader::class);
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->salesChannelCmsPageLoader = $this->getContainer()->get(SalesChannelCmsPageLoader::class);
    }

    public function testSlotOverwrite(): void
    {
        $salesChannelContext = $this->createSalesChannelContext();

        $category = [
            'id' => Uuid::randomHex(),
            'name' => 'test category',
            'cmsPage' => [
                'id' => Uuid::randomHex(),
                'name' => 'test page',
                'type' => 'landingpage',
                'blocks' => [
                    [
                        'type' => 'text',
                        'position' => 0,
                        'slots' => [
                            [
                                'id' => Uuid::randomHex(),
                                'type' => 'text',
                                'slot' => 'content',
                                'config' => [
                                    'content' => [
                                        'source' => 'static',
                                        'value' => 'initial',
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

        static::assertGreaterThanOrEqual(1, $pages->getTotal());

        /** @var CmsPageEntity $page */
        $page = $pages->first();

        $fieldConfigCollection = new FieldConfigCollection([new FieldConfig('content', 'static', 'initial')]);

        static::assertEquals(
            $category['cmsPage']['blocks'][0]['slots'][0]['config'],
            $page->getBlocks()->getSlots()->first()->getConfig()
        );

        static::assertEquals(
            $fieldConfigCollection,
            $page->getBlocks()->getSlots()->first()->getFieldConfig()
        );

        // overwrite in category
        $customSlotConfig = [
            $category['cmsPage']['blocks'][0]['slots'][0]['id'] => [
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
            $customSlotConfig[$category['cmsPage']['blocks'][0]['slots'][0]['id']],
            $page->getBlocks()->getSlots()->first()->getConfig()
        );

        static::assertEquals(
            $fieldConfigCollection,
            $page->getBlocks()->getSlots()->first()->getFieldConfig()
        );
    }

    protected function getPageLoader()
    {
        return $this->navigationPageLoader;
    }
}
