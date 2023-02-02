<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamMappingIndexingMessage;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexingMessage;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    private EntityRepository $productRepository;

    private EntityRepository $productStreamRepository;

    private SalesChannelContext $salesChannel;

    private ProductStreamUpdater $productStreamUpdater;

    public function setUp(): void
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->productStreamRepository = $this->getContainer()->get('product_stream.repository');
        $this->salesChannel = $this->getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $this->productStreamUpdater = $this->getContainer()->get(ProductStreamUpdater::class);
    }

    /**
     * @param array<int, array<string, array<string, int>|string>> $filters
     *
     * @dataProvider filterProvider
     */
    public function testIndexingDoesUpdateMappingsAndManyToManyIdField(array $filters): void
    {
        $streamId = Uuid::randomHex();
        $stream = [
            'id' => $streamId,
            'name' => 'test',
            'filters' => $filters,
        ];

        $writtenEvent = $this->productStreamRepository->create([$stream], Context::createDefaultContext());

        $productStreamIndexer = $this->getContainer()->get(ProductStreamIndexer::class);
        $message = $productStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(ProductStreamIndexingMessage::class, $message);
        $productStreamIndexer->handle($message);

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

    /**
     * @return iterable<string, array<int, array<int, array<string, array<string, int>|string>>>>
     */
    public function filterProvider(): iterable
    {
        yield 'Active filter' => [
            [[
                'type' => 'equals',
                'field' => 'active',
                'value' => '1',
            ]],
        ];

        yield 'Price filter / default price' => [
            [[
                'type' => 'range',
                'field' => 'cheapestPrice',
                'parameters' => [
                    'gte' => 100,
                ],
            ]],
        ];

        yield 'Price filter / advanced price' => [
            [[
                'type' => 'range',
                'field' => 'cheapestPrice',
                'parameters' => [
                    'lte' => 50,
                ],
            ]],
        ];

        yield 'Price filter / default list price percentage' => [
            [[
                'type' => 'range',
                'field' => 'cheapestPrice.percentage',
                'parameters' => [
                    'gte' => 50,
                ],
            ]],
        ];

        yield 'Price filter / advanced list price percentage' => [
            [[
                'type' => 'range',
                'field' => 'cheapestPrice.percentage',
                'parameters' => [
                    'lt' => 50,
                ],
            ]],
        ];
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
        $message = $productStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(ProductStreamIndexingMessage::class, $message);
        $productStreamIndexer->handle($message);

        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        // If this call does not throw an exception, everything is ok
        $this->productStreamUpdater->updateProducts([$productId], Context::createDefaultContext());
    }

    private function createProduct(string $productId): void
    {
        $this->productRepository->create(
            [
                [
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
                            'listPrice' => ['gross' => 200, 'net' => 200, 'linked' => false],
                        ],
                    ],
                    'prices' => [
                        [
                            'quantityStart' => 1,
                            'rule' => [
                                'name' => 'Test rule',
                                'priority' => 1,
                            ],
                            'price' => [
                                [
                                    'currencyId' => Defaults::CURRENCY,
                                    'gross' => 50,
                                    'net' => 9, 'linked' => false,
                                    'listPrice' => ['gross' => 60, 'net' => 60, 'linked' => false],
                                ],
                            ],
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
                ],
            ],
            $this->salesChannel->getContext()
        );
    }
}
