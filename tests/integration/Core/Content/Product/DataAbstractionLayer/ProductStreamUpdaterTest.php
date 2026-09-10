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
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter\ProductStreamFilterCollection;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexer;
use Shopware\Core\Content\ProductStream\DataAbstractionLayer\ProductStreamIndexingMessage;
use Shopware\Core\Content\ProductStream\ProductStreamCollection;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
#[Package('framework')]
class ProductStreamUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

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

    /**
     * @var EntityRepository<ProductStreamFilterCollection>
     */
    private EntityRepository $productStreamFilterRepository;

    private SalesChannelContext $salesChannel;

    private ProductStreamUpdater $productStreamUpdater;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->productStreamRepository = static::getContainer()->get('product_stream.repository');
        $this->salesChannelLanguageRepository = static::getContainer()->get('sales_channel_language.repository');
        $this->productStreamFilterRepository = static::getContainer()->get('product_stream_filter.repository');
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

    public function testDeletingAFilterUpdatesTheMapping(): void
    {
        $streamId = Uuid::randomHex();
        $stockFilterId = Uuid::randomHex();

        $this->createStream($streamId, [
            [
                'type' => 'equals',
                'field' => 'active',
                'value' => '1',
            ],
            [
                'id' => $stockFilterId,
                'type' => 'equals',
                'field' => 'stock',
                'value' => '999',
            ],
        ]);

        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        $this->productStreamUpdater->handle(new ProductStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext()));
        $this->assertProductIsNotInStream($productId, $streamId);

        $this->clearQueue();

        // the product only matches once the stock condition is gone
        $deleteEvent = $this->productStreamFilterRepository->delete([['id' => $stockFilterId]], Context::createDefaultContext());

        $this->recompileStreamFilters($deleteEvent);
        $this->handleDispatchedMappingMessages($streamId);

        $this->assertProductIsInStream($productId, $streamId);
    }

    public function testUpdatingAnExistingFilterUpdatesTheMapping(): void
    {
        $streamId = Uuid::randomHex();
        $stockFilterId = Uuid::randomHex();

        $this->createStream($streamId, [
            [
                'id' => $stockFilterId,
                'type' => 'equals',
                'field' => 'stock',
                'value' => '999',
            ],
        ]);

        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        $this->productStreamUpdater->handle(new ProductStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext()));
        $this->assertProductIsNotInStream($productId, $streamId);

        $this->clearQueue();

        // only the value changes, so the write payload carries no product stream id
        $updateEvent = $this->productStreamFilterRepository->update(
            [['id' => $stockFilterId, 'value' => '1']],
            Context::createDefaultContext()
        );

        $this->recompileStreamFilters($updateEvent);
        $this->handleDispatchedMappingMessages($streamId);

        $this->assertProductIsInStream($productId, $streamId);
    }

    /**
     * A reassignment names only the new stream in its payload. The stream losing the filter must be
     * re-indexed too, which is only possible from the previous row state.
     */
    public function testReassigningAFilterUpdatesTheMappingOfBothStreams(): void
    {
        // a stock value that no other fixture uses keeps both streams scoped to these two products
        $stock = 4242;
        $matchingProductId = Uuid::randomHex();
        $otherProductId = Uuid::randomHex();
        $this->createProduct($matchingProductId, $stock);
        $this->createProduct($otherProductId, $stock);

        $oldStreamId = Uuid::randomHex();
        $newStreamId = Uuid::randomHex();
        $filterId = Uuid::randomHex();

        // the product number condition narrows the old stream down to one of the two products
        $this->createStream($oldStreamId, [
            [
                'type' => 'equals',
                'field' => 'stock',
                'value' => (string) $stock,
            ],
            [
                'id' => $filterId,
                'type' => 'equals',
                'field' => 'productNumber',
                'value' => $matchingProductId,
            ],
        ]);
        $this->createStream($newStreamId, [
            [
                'type' => 'equals',
                'field' => 'stock',
                'value' => (string) $stock,
            ],
        ]);

        $context = Context::createDefaultContext();
        $this->productStreamUpdater->handle(new ProductStreamMappingIndexingMessage($oldStreamId, null, $context));
        $this->productStreamUpdater->handle(new ProductStreamMappingIndexingMessage($newStreamId, null, $context));
        $this->assertProductIsNotInStream($otherProductId, $oldStreamId);
        $this->assertProductIsInStream($otherProductId, $newStreamId);

        $this->clearQueue();

        $reassignEvent = $this->productStreamFilterRepository->update(
            [['id' => $filterId, 'productStreamId' => $newStreamId]],
            $context
        );

        $this->recompileStreamFilters($reassignEvent);
        $this->handleDispatchedMappingMessages($oldStreamId, $newStreamId);

        // the old stream lost its narrowing condition and now matches both products
        $this->assertProductIsInStream($otherProductId, $oldStreamId);
        // the new stream gained it and no longer matches the second product
        $this->assertProductIsNotInStream($otherProductId, $newStreamId);
    }

    /**
     * A group that lost its last condition must stop matching. The indexer returns no filter rows for
     * it, so it has to be compiled to an empty api_filter rather than keeping its previous one.
     */
    public function testRemovingTheLastFilterClearsTheMapping(): void
    {
        $streamId = Uuid::randomHex();
        $filterId = Uuid::randomHex();

        $productId = Uuid::randomHex();
        $this->createProduct($productId);

        $this->createStream($streamId, [
            [
                'id' => $filterId,
                'type' => 'equals',
                'field' => 'productNumber',
                'value' => $productId,
            ],
        ]);

        $this->productStreamUpdater->handle(new ProductStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext()));
        $this->assertProductIsInStream($productId, $streamId);

        $this->clearQueue();

        $deleteEvent = $this->productStreamFilterRepository->delete([['id' => $filterId]], Context::createDefaultContext());

        $this->recompileStreamFilters($deleteEvent);
        $this->handleDispatchedMappingMessages($streamId);

        $this->assertProductIsNotInStream($productId, $streamId);
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
     * @param array<int, array<string, mixed>> $filters
     */
    private function createStream(string $streamId, array $filters): void
    {
        $writtenEvent = $this->productStreamRepository->create([[
            'id' => $streamId,
            'name' => 'test',
            'filters' => $filters,
        ]], Context::createDefaultContext());

        $this->recompileStreamFilters($writtenEvent);
    }

    private function recompileStreamFilters(EntityWrittenContainerEvent $event): void
    {
        $productStreamIndexer = static::getContainer()->get(ProductStreamIndexer::class);

        $message = $productStreamIndexer->update($event);
        static::assertInstanceOf(
            ProductStreamIndexingMessage::class,
            $message,
            'expected the write to request a re-compilation of the stream filters'
        );

        $productStreamIndexer->handle($message);
    }

    private function handleDispatchedMappingMessages(string ...$expectedStreamIds): void
    {
        $bus = static::getContainer()->get('messenger.bus.test_shopware');
        static::assertInstanceOf(TraceableMessageBus::class, $bus);

        $handled = [];
        foreach ($bus->getDispatchedMessages() as $dispatched) {
            $message = $dispatched['message'];
            if (!$message instanceof ProductStreamMappingIndexingMessage) {
                continue;
            }

            $handled[] = $message->getData();
            $this->productStreamUpdater->handle($message);
        }

        foreach ($expectedStreamIds as $expectedStreamId) {
            static::assertContains(
                $expectedStreamId,
                $handled,
                'expected the write to dispatch a mapping update for the affected stream'
            );
        }
    }

    private function assertProductIsNotInStream(string $productId, string $streamId): void
    {
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('streams');
        $product = $this->productRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertNotContains($streamId, $product->getStreamIds() ?? []);
    }

    private function createProduct(string $productId, int $stock = 1): void
    {
        $this->productRepository->create(
            [
                $this->getProductData($productId, $stock),
            ],
            $this->salesChannel->getContext()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getProductData(string $productId, int $stock = 1): array
    {
        return [
            'id' => $productId,
            'productNumber' => $productId,
            'stock' => $stock,
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
