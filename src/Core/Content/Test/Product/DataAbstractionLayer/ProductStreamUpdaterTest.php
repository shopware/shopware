<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamMappingIndexingMessage;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class ProductStreamUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepositoryInterface $productRepository;

    private EntityRepositoryInterface $productStreamRepository;

    private SalesChannelContext $salesChannel;

    private ProductStreamUpdater $productStreamUpdater;

    public function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->productStreamRepository = $this->getContainer()->get('product_stream.repository');
        $this->salesChannel = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $this->productStreamUpdater = $this->getContainer()->get(ProductStreamUpdater::class);
    }

    public function testIndexingDoesUpdateMappingsAndManyToManyIdField(): void
    {
        $streamId = Uuid::randomHex();
        $stream = [
            'id' => $streamId,
            'name' => 'test',
            'filters' => [[
                'type' => 'equals',
                'field' => 'active',
                'value' => '1',
            ]],
        ];

        $writtenEvent = $this->productStreamRepository->create([$stream], Context::createDefaultContext());

        $productStreamIndexer = $this->getContainer()->get(ProductStreamIndexer::class);
        $productStreamIndexer->handle(
            $productStreamIndexer->update($writtenEvent)
        );

        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        $message = new ProductStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext());
        $this->productStreamUpdater->handle($message);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('streams');
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->first();

        static::assertEquals(1, $product->getStreams()->count());
        static::assertEquals($streamId, $product->getStreams()->first()->getId());
        static::assertContains($streamId, $product->getStreamIds());
    }

    public function testIndexingDoesNotBreakOnInvalidProductStreamFilters(): void
    {
        $stream = [
            'name' => 'test',
            'filters' => [[
                'type' => 'equals',
                'field' => 'doesNotExist',
                'value' => '100',
            ]],
        ];

        $writtenEvent = $this->productStreamRepository->create([$stream], Context::createDefaultContext());

        $productStreamIndexer = $this->getContainer()->get(ProductStreamIndexer::class);
        $productStreamIndexer->handle(
            $productStreamIndexer->update($writtenEvent)
        );

        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        // If this call does not throw an exception, everything is ok
        $this->productStreamUpdater->updateProducts([$productId], Context::createDefaultContext());
    }

    public function testConsiderInheritanceVariants(): void
    {
        $activeStreamId = Uuid::randomHex();
        $inActiveStreamId = Uuid::randomHex();

        $writtenEvent = $this->productStreamRepository->create([
            [
                'id' => $activeStreamId,
                'name' => 'test-inheritance',
                'filters' => [
                    [
                        'type' => 'equals',
                        'field' => 'active',
                        'value' => '1',
                    ],
                ],
            ],
            [
                'id' => $inActiveStreamId,
                'name' => 'test-inheritance',
                'filters' => [
                    [
                        'type' => 'equals',
                        'field' => 'active',
                        'value' => '0',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $productStreamIndexer = $this->getContainer()->get(ProductStreamIndexer::class);
        $productStreamIndexer->handle(
            $productStreamIndexer->update($writtenEvent)
        );

        $productId = Uuid::randomHex();
        $products = [$this->getProductData($productId)];

        // Get product data [variantId => active]
        $variantIds = [
            Uuid::randomHex() => null,
            Uuid::randomHex() => false,
            Uuid::randomHex() => true,
        ];

        foreach ($variantIds as $id => $active) {
            $productData = $this->getProductData($id);
            $productData['parentId'] = $productId;
            $productData['active'] = $active;
            $products[] = $productData;
        }

        // Create all (4) products at once (fastest)
        $this->productRepository->create(
            $products,
            $this->salesChannel->getContext()
        );

        // Index both active & inactive product_stream
        $this->productStreamUpdater->handle(new ProductStreamMappingIndexingMessage(
            [$activeStreamId, $inActiveStreamId],
            null,
            $this->salesChannel->getContext()
        ));

        $productIds = array_keys($variantIds);
        $productIds[] = $productId;

        // Valid product_stream for active products.
        $activeProducts = $this->productRepository->search(
            (new Criteria($productIds))
                ->addFilter(new EqualsFilter('streams.id', $activeStreamId))
                ->addAssociation('streams'),
            $this->salesChannel->getContext()
        )->getEntities();
        // Check product & stream count is correct
        static::assertEquals(3, $activeProducts->count());
        static::assertEquals(
            3,
            $activeProducts->filter(function (ProductEntity $product) use ($activeStreamId) {
                return $product->getStreams()
                    ->filterByProperty('id', $activeStreamId)
                    ->first();
            })->count()
        );
        // Check and ensure the opposite product_stream (inactive) weren't added
        static::assertEquals(
            0,
            $activeProducts->filter(function (ProductEntity $product) use ($inActiveStreamId) {
                return $product->getStreams()
                    ->filterByProperty('id', $inActiveStreamId)
                    ->first();
            })->count()
        );

        // Valid product_stream for inactive products.
        $inActiveProducts = $this->productRepository->search(
            (new Criteria($productIds))
                ->addFilter(new EqualsFilter('streams.id', $inActiveStreamId))
                ->addAssociation('streams'),
            $this->salesChannel->getContext()
        )->getEntities();
        // Check product & stream count is correct
        static::assertEquals(1, $inActiveProducts->count());
        static::assertEquals(
            1,
            $inActiveProducts->filter(function (ProductEntity $product) use ($inActiveStreamId) {
                return $product->getStreams()
                    ->filterByProperty('id', $inActiveStreamId)
                    ->first();
            })->count()
        );
        // Check and ensure the opposite product_stream (active) weren't added
        static::assertEquals(
            0,
            $inActiveProducts->filter(function (ProductEntity $product) use ($activeStreamId) {
                return $product->getStreams()
                    ->filterByProperty('id', $activeStreamId)
                    ->first();
            })->count()
        );
    }

    private function createProduct(string $productId): void
    {
        $this->productRepository->create(
            [$this->getProductData($productId)],
            $this->salesChannel->getContext()
        );
    }

    private function getProductData(string $productId): array
    {
        return [
            'id' => $productId,
            'productNumber' => $productId,
            'stock' => 1,
            'name' => 'Test',
            'active' => true,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 100,
                    'net' => 9, 'linked' => false,
                ],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['taxRate' => 19, 'name' => 'with id'],
            'visibilities' => [
                ['salesChannelId' => $this->salesChannel->getSalesChannel()->getId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'categories' => [
                ['id' => Uuid::randomHex(), 'name' => 'Clothing'],
            ],
        ];
    }
}
