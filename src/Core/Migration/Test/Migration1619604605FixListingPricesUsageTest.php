<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Faker\Factory;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1619604605FixListingPricesUsage;

class Migration1619604605FixListingPricesUsageTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepositoryInterface $cmsPageRepository;

    public function setUp(): void
    {
        $this->cmsPageRepository = $this->getContainer()->get('cms_page.repository');
    }

    public function testCategorySettingsWithListingPrices(): void
    {
        $cmsIds = $this->createCmsPage();

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('category.repository');

        $criteria = (new Criteria())->setLimit(1);

        $categoryId = $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];

        $repository->update([
            [
                'id' => $categoryId,
                'cmsPageId' => $cmsIds['pageId'],
                'slotConfig' => [
                    $cmsIds['sortingSlotId'] => [
                        'productStreamSorting' => [
                            'source' => 'static',
                            'value' => 'listingPrices:ASC',
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $migration = new Migration1619604605FixListingPricesUsage();
        $migration->update($this->getContainer()->get(Connection::class));

        /** @var CategoryEntity $category */
        $category = $repository->search(new Criteria([$categoryId]), Context::createDefaultContext())->first();

        static::assertEquals([
            $cmsIds['sortingSlotId'] => [
                'productStreamSorting' => [
                    'source' => 'static',
                    'value' => 'cheapestPrice:ASC',
                ],
            ],
        ], $category->getSlotConfig());
    }

    public function testCategorySettingsWithPurchasePrice(): void
    {
        $cmsIds = $this->createCmsPage();

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('category.repository');

        $criteria = (new Criteria())->setLimit(1);

        $categoryId = $repository->searchIds($criteria, Context::createDefaultContext())->getIds()[0];

        $repository->update([
            [
                'id' => $categoryId,
                'cmsPageId' => $cmsIds['pageId'],
                'slotConfig' => [
                    $cmsIds['sortingSlotId'] => [
                        'productStreamSorting' => [
                            'source' => 'static',
                            'value' => 'purchasePrice:ASC',
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $migration = new Migration1619604605FixListingPricesUsage();
        $migration->update($this->getContainer()->get(Connection::class));

        /** @var CategoryEntity $category */
        $category = $repository->search(new Criteria([$categoryId]), Context::createDefaultContext())->first();

        static::assertEquals([
            $cmsIds['sortingSlotId'] => [
                'productStreamSorting' => [
                    'source' => 'static',
                    'value' => 'purchasePrices:ASC',
                ],
            ],
        ], $category->getSlotConfig());
    }

    public function testStreamsWithListingPrices(): void
    {
        $ids = new IdsCollection();

        $stream = [
            'id' => $ids->get('stream'),
            'name' => 'test',
            'filters' => [[
                'id' => $ids->get('filters'),
                'type' => 'equals',
                'field' => 'listingPrices',
                'value' => '100',
            ]],
        ];

        $writtenEvent = $this->getContainer()->get('product_stream.repository')
            ->create([$stream], $ids->getContext());

        $productStreamIndexer = $this->getContainer()->get(ProductStreamIndexer::class);
        $productStreamIndexer->handle(
            $productStreamIndexer->update($writtenEvent)
        );

        $migration = new Migration1619604605FixListingPricesUsage();
        $migration->update($this->getContainer()->get(Connection::class));

        $criteria = new Criteria([$ids->get('stream')]);
        $criteria->addAssociation('filters');
        /** @var ProductStreamEntity $stream */
        $stream = $this->getContainer()->get('product_stream.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals([[
            'type' => 'equals',
            'field' => 'product.cheapestPrice',
            'value' => '100',
        ]], $stream->getApiFilter());

        static::assertEquals('cheapestPrice', $stream->getFilters()->first()->getField());
    }

    public function testStreamsWithPurchasePrice(): void
    {
        $ids = new IdsCollection();

        $stream = [
            'id' => $ids->get('stream'),
            'name' => 'test',
            'filters' => [[
                'id' => $ids->get('filters'),
                'type' => 'equals',
                'field' => 'purchasePrice',
                'value' => '100',
            ]],
        ];

        $writtenEvent = $this->getContainer()->get('product_stream.repository')
            ->create([$stream], $ids->getContext());

        $productStreamIndexer = $this->getContainer()->get(ProductStreamIndexer::class);
        $productStreamIndexer->handle(
            $productStreamIndexer->update($writtenEvent)
        );

        $migration = new Migration1619604605FixListingPricesUsage();
        $migration->update($this->getContainer()->get(Connection::class));

        $criteria = new Criteria([$ids->get('stream')]);
        $criteria->addAssociation('filters');
        /** @var ProductStreamEntity $stream */
        $stream = $this->getContainer()->get('product_stream.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals([[
            'type' => 'equals',
            'field' => 'product.purchasePrices',
            'value' => '100',
        ]], $stream->getApiFilter());

        static::assertEquals('purchasePrices', $stream->getFilters()->first()->getField());

        // Test it does not modify purchasePrices
        $migration = new Migration1619604605FixListingPricesUsage();
        $migration->update($this->getContainer()->get(Connection::class));

        $criteria = new Criteria([$ids->get('stream')]);
        $criteria->addAssociation('filters');
        /** @var ProductStreamEntity $stream */
        $stream = $this->getContainer()->get('product_stream.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals([[
            'type' => 'equals',
            'field' => 'product.purchasePrices',
            'value' => '100',
        ]], $stream->getApiFilter());

        static::assertEquals('purchasePrices', $stream->getFilters()->first()->getField());
    }

    public function testCmsSlotConfigWithPurchasePrice(): void
    {
        $cmsIds = $this->createCmsPage([
            'source' => 'static',
            'value' => 'purchasePrice:ASC',
        ]);

        $migration = new Migration1619604605FixListingPricesUsage();
        $migration->update($this->getContainer()->get(Connection::class));

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('cms_slot.repository');

        /** @var CmsSlotEntity $cmsSlot */
        $cmsSlot = $repository->search(new Criteria([$cmsIds['sortingSlotId']]), Context::createDefaultContext())->first();

        static::assertEquals([
            'source' => 'static',
            'value' => 'purchasePrices:ASC',
        ], $cmsSlot->getConfig()['productStreamSorting']);
    }

    public function testProductSortingWithPurchasePrice(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'key' => Uuid::randomHex(),
            'priority' => 0,
            'active' => true,
            'fields' => [
                ['field' => 'product.purchasePrice', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => 1],
            ],
            'label' => 'test',
        ];

        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('product_sorting.repository');
        $repository->create([$data], Context::createDefaultContext());

        $migration = new Migration1619604605FixListingPricesUsage();
        $migration->update($this->getContainer()->get(Connection::class));

        /** @var ProductSortingEntity $productSorting */
        $productSorting = $repository->search(new Criteria([$id]), Context::createDefaultContext())->first();

        static::assertEquals([
            ['field' => 'product.purchasePrices', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => 1],
        ], $productSorting->getFields());

        // Test it does not modify purchasePrices
        $migration = new Migration1619604605FixListingPricesUsage();
        $migration->update($this->getContainer()->get(Connection::class));

        /** @var ProductSortingEntity $productSorting */
        $productSorting = $repository->search(new Criteria([$id]), Context::createDefaultContext())->first();

        static::assertEquals([
            ['field' => 'product.purchasePrices', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => 1],
        ], $productSorting->getFields());
    }

    private function createCmsPage(?array $sorting = null): array
    {
        $faker = Factory::create();
        $sortingSlotId = Uuid::randomHex();

        if (!$sorting) {
            $sorting = [
                'source' => 'static',
                'value' => 'name:ASC',
            ];
        }

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
                                ['id' => $sortingSlotId, 'type' => 'image', 'slot' => 'right', 'config' => ['url' => ['source' => FieldConfig::SOURCE_STATIC, 'value' => 'http://shopware.com/image.jpg'], 'productStreamSorting' => $sorting]],
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

        $this->cmsPageRepository->create([$page], Context::createDefaultContext());

        return [
            'pageId' => $page['id'],
            'sortingSlotId' => $sortingSlotId,
        ];
    }
}
