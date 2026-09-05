<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamMappingIndexingMessage;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexingMessage;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('framework')]
class ProductStreamUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<ProductStreamCollection>
     */
    private EntityRepository $productStreamRepository;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $salesChannelLanguageRepository;

    private SalesChannelContext $salesChannel;

    private ProductStreamUpdater $productStreamUpdater;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->productStreamRepository = static::getContainer()->get('product_stream.repository');
        $this->salesChannelLanguageRepository = static::getContainer()->get('sales_channel_language.repository');
        $this->salesChannel = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $this->productStreamUpdater = static::getContainer()->get(ProductStreamUpdater::class);
    }

    /**
     * @param array<int, array<string, array<string, int>|string>> $filters
     */
    #[DataProvider('filterProvider')]
    public function testIndexingDoesUpdateMappingsAndManyToManyIdField(array $filters): void
    {
        $streamId = Uuid::randomHex();
        $stream = [
            'id' => $streamId,
            'name' => 'test',
            'filters' => $filters,
        ];

        $writtenEvent = $this->productStreamRepository->create([$stream], Context::createDefaultContext());

        $productStreamIndexer = static::getContainer()->get(ProductStreamIndexer::class);
        $message = $productStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(ProductStreamIndexingMessage::class, $message);
        $productStreamIndexer->handle($message);

        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        $message = new ProductStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext());
        $this->productStreamUpdater->handle($message);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('streams');
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ProductEntity::class, $product);

        $streams = $product->getStreams();
        static::assertNotNull($streams);
        static::assertCount(1, $streams);
        $firstStream = $streams->first();
        static::assertInstanceOf(ProductStreamEntity::class, $firstStream);
        static::assertSame($streamId, $firstStream->getId());
        static::assertIsArray($product->getStreamIds());
        static::assertContains($streamId, $product->getStreamIds());
    }

    /**
     * @return iterable<string, array<int, array<int, array<string, array<string, int>|string>>>>
     */
    public static function filterProvider(): iterable
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
                    'gt' => 50,
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

        $productStreamIndexer = static::getContainer()->get(ProductStreamIndexer::class);
        $message = $productStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(ProductStreamIndexingMessage::class, $message);
        $productStreamIndexer->handle($message);

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

        $productStreamIndexer = static::getContainer()->get(ProductStreamIndexer::class);
        $update = $productStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(EntityIndexingMessage::class, $update);

        $productStreamIndexer->handle($update);

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
        static::assertCount(3, $activeProducts);
        static::assertCount(
            3,
            $activeProducts->filter(static function (ProductEntity $product) use ($activeStreamId) {
                $streams = $product->getStreams();
                if ($streams) {
                    return $streams->filterByProperty('id', $activeStreamId)
                        ->first() !== null;
                }

                return false;
            })
        );
        // Check and ensure the opposite product_stream (inactive) weren't added
        static::assertCount(
            0,
            $activeProducts->filter(static function (ProductEntity $product) use ($inActiveStreamId) {
                $streams = $product->getStreams();
                if ($streams) {
                    return $streams->filterByProperty('id', $inActiveStreamId)
                        ->first() !== null;
                }

                return false;
            })
        );

        // Valid product_stream for inactive products.
        $inActiveProducts = $this->productRepository->search(
            (new Criteria($productIds))
                ->addFilter(new EqualsFilter('streams.id', $inActiveStreamId))
                ->addAssociation('streams'),
            $this->salesChannel->getContext()
        )->getEntities();
        // Check product & stream count is correct
        static::assertCount(1, $inActiveProducts);
        static::assertCount(
            1,
            $inActiveProducts->filter(static function (ProductEntity $product) use ($inActiveStreamId) {
                $streams = $product->getStreams();
                if ($streams) {
                    return $streams->filterByProperty('id', $inActiveStreamId)
                        ->first() !== null;
                }

                return false;
            })
        );
        // Check and ensure the opposite product_stream (active) weren't added
        static::assertCount(
            0,
            $inActiveProducts->filter(static function (ProductEntity $product) use ($activeStreamId) {
                $streams = $product->getStreams();
                if ($streams) {
                    return $streams->filterByProperty('id', $activeStreamId)
                        ->first() !== null;
                }

                return false;
            })
        );
    }

    public function testProductStreamIndexingConsidersNonDefaultLanguageCustomFields(): void
    {
        $languageId = $this->createAssignedLanguage('de-DE-' . Uuid::randomHex(), 'Test locale', 'Test', 'Test language');
        $streamId = $this->createCustomFieldStream('Custom field stream');
        $productId = $this->createTranslatedProductWithNonDefaultLanguageCustomFieldMatch($languageId);

        $indexMessage = new ProductStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext());
        $this->productStreamUpdater->handle($indexMessage);

        $this->assertProductIsInStream($productId, $streamId);
    }

    public function testUpdateProductsConsidersNonDefaultLanguageCustomFields(): void
    {
        $languageId = $this->createAssignedLanguage('de-DE-' . Uuid::randomHex(), 'Update locale', 'Update', 'Update language');
        $streamId = $this->createCustomFieldStream('Custom field stream update');
        $productId = $this->createTranslatedProductWithNonDefaultLanguageCustomFieldMatch($languageId);

        $this->productStreamUpdater->updateProducts([$productId], Context::createDefaultContext());

        $this->assertProductIsInStream($productId, $streamId);
    }

    /**
     * Regression test for https://github.com/shopware/shopware/issues/10770.
     *
     * A product stream whose conditions traverse many associations used to produce
     * the error 1116 ("Too many tables; MariaDB can only use 61 tables in a join")
     * while indexing, because every condition added its joins to one query. The DAL
     * now resolves the filter-only associations of such a criteria as `EXISTS` sub
     * queries, which do not count towards the limit.
     */
    public function testIndexingHandlesStreamsWithMoreThanSixtyOneConditions(): void
    {
        $streamId = Uuid::randomHex();

        $conditions = $this->buildManyDistinctAssociationConditions();
        static::assertGreaterThan(61, \count($conditions));

        $writtenEvent = $this->productStreamRepository->create([
            [
                'id' => $streamId,
                'name' => 'large-stream',
                // Shape the Administration always produces: a root OR container
                // holding AND groups, see the `getOrContainerData()` /
                // `getAndContainerData()` helpers of `product-stream-condition.service.js`.
                'filters' => [
                    [
                        'type' => 'multi',
                        'operator' => 'OR',
                        'queries' => [
                            [
                                'type' => 'multi',
                                'operator' => 'AND',
                                'queries' => $conditions,
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $productStreamIndexer = static::getContainer()->get(ProductStreamIndexer::class);
        $update = $productStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(ProductStreamIndexingMessage::class, $update);
        $productStreamIndexer->handle($update);

        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        // Without the fix both calls build a single criteria that joins far more
        // than 61 tables and throw Doctrine\DBAL\Exception with
        // SQLSTATE[HY000]: General error: 1116 (Too many tables ...).
        $message = new ProductStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext());
        $this->productStreamUpdater->handle($message);

        $this->productStreamUpdater->updateProducts([$productId], Context::createDefaultContext());

        // The conditions reference relations that do not exist, so the product must
        // not be mapped - the point is that the stream can be indexed at all.
        $this->assertProductIsNotInStream($productId, $streamId);

        // ... while a stream the product does match is still indexed correctly
        $matchingStreamId = $this->createStream([[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $this->productStreamUpdater->handle(
            new ProductStreamMappingIndexingMessage($matchingStreamId, null, Context::createDefaultContext())
        );

        $this->assertProductIsInStream($productId, $matchingStreamId);
    }

    /**
     * @param list<array<string, mixed>> $conditions
     */
    private function createStream(array $conditions): string
    {
        $streamId = Uuid::randomHex();

        $writtenEvent = $this->productStreamRepository->create([
            [
                'id' => $streamId,
                'name' => 'stream-' . $streamId,
                'filters' => $conditions,
            ],
        ], Context::createDefaultContext());

        $indexer = static::getContainer()->get(ProductStreamIndexer::class);
        $message = $indexer->update($writtenEvent);
        static::assertInstanceOf(ProductStreamIndexingMessage::class, $message);
        $indexer->handle($message);

        return $streamId;
    }

    private function assertProductIsNotInStream(string $productId, string $streamId): void
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('streams');
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertNotContains($streamId, $product->getStreamIds() ?? []);
    }

    /**
     * Builds a list of AND conditions whose fields each traverse a DISTINCT
     * association path. Distinct paths each add their own SQL join(s), so the
     * full conjunction joins well over 61 tables in a single query — while any
     * chunk of <= CONDITION_CHUNK_SIZE conditions stays comfortably below the
     * limit. (Repeating the SAME path would be collapsed into one join / an
     * EXISTS subquery and would NOT reproduce the issue.)
     *
     * The conditions carry a value on purpose. A null check keeps its left join
     * even when the criteria spills into sub queries, because inside an `EXISTS`
     * it would stop matching records without the association at all.
     *
     * @return list<array<string, string>>
     */
    private function buildManyDistinctAssociationConditions(): array
    {
        // Each leaf traverses at least one association (and most a translation),
        // so it contributes one or more joins that cannot be shared with the others.
        $leaves = [
            'manufacturer.name',
            'tax.name',
            'unit.name',
            'deliveryTime.name',
            'cmsPage.name',
            'featureSet.name',
            'cover.id',
            'categories.name',
            'properties.name',
            'options.name',
            'tags.name',
            'categoriesRo.name',
            'media.id',
            'prices.quantityStart',
            'visibilities.id',
            'productReviews.id',
            'mainCategories.id',
            'seoUrls.id',
            'crossSellings.id',
            'configuratorSettings.id',
        ];

        // Re-root every leaf through self-referencing to-one associations to
        // multiply the number of distinct paths far beyond the 61-table limit.
        // The created product has neither a parent nor a canonical product, so
        // every one of these paths resolves to NULL for it.
        $prefixes = ['parent.', 'canonicalProduct.', 'parent.parent.', 'canonicalProduct.parent.'];

        $conditions = [];
        foreach ($prefixes as $prefix) {
            foreach ($leaves as $leaf) {
                $conditions[] = [
                    'type' => 'equals',
                    'field' => $prefix . $leaf,
                    'value' => Uuid::randomHex(),
                ];
            }
        }

        return $conditions;
    }

    private function createProduct(string $productId): void
    {
        $this->productRepository->create(
            [
                $this->getProductData($productId),
            ],
            $this->salesChannel->getContext()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getProductData(string $productId): array
    {
        return [
            'id' => $productId,
            'productNumber' => $productId,
            'stock' => 1,
            'name' => 'Test',
            'active' => true,
            'type' => ProductDefinition::TYPE_PHYSICAL,
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
                ['salesChannelId' => $this->salesChannel->getSalesChannelId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'categories' => [
                ['id' => Uuid::randomHex(), 'name' => 'Clothing'],
            ],
        ];
    }

    private function createCustomFieldStream(string $name): string
    {
        $streamId = Uuid::randomHex();
        $writtenEvent = $this->productStreamRepository->create([
            [
                'id' => $streamId,
                'name' => $name,
                'filters' => [
                    [
                        'type' => 'equals',
                        'field' => 'customFields.test_stream_checkbox',
                        'value' => 'active',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $productStreamIndexer = static::getContainer()->get(ProductStreamIndexer::class);
        $message = $productStreamIndexer->update($writtenEvent);

        static::assertInstanceOf(ProductStreamIndexingMessage::class, $message);

        $productStreamIndexer->handle($message);

        return $streamId;
    }

    private function createTranslatedProductWithNonDefaultLanguageCustomFieldMatch(string $languageId): string
    {
        $productId = Uuid::randomHex();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productId,
                'stock' => 1,
                'active' => true,
                'price' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 100,
                        'net' => 9,
                        'linked' => false,
                    ],
                ],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'with id'],
                'visibilities' => [
                    ['salesChannelId' => $this->salesChannel->getSalesChannelId(), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
                'translations' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'name' => 'Test Product',
                        'customFields' => null,
                    ],
                    [
                        'languageId' => $languageId,
                        'name' => 'Test Product Non-Default',
                        'customFields' => ['test_stream_checkbox' => 'active'],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $productId;
    }

    private function assertProductIsInStream(string $productId, string $streamId): void
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('streams');
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertIsArray($product->getStreamIds());
        static::assertContains($streamId, $product->getStreamIds());
    }

    private function createAssignedLanguage(string $localeCode, string $localeName, string $territory, string $languageName): string
    {
        $localeId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        /** @var EntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = static::getContainer()->get('locale.repository');
        $localeRepository->create([
            ['id' => $localeId, 'code' => $localeCode, 'name' => $localeName, 'territory' => $territory],
        ], $context);

        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $languageRepository->create([
            [
                'id' => $languageId,
                'name' => $languageName,
                'localeId' => $localeId,
                'translationCodeId' => $localeId,
            ],
        ], $context);

        $this->salesChannelLanguageRepository->create([
            [
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'languageId' => $languageId,
            ],
        ], $context);

        return $languageId;
    }
}
