<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Events\ProductListingResolvePreviewEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Group('slow')]
class ProductListingLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private ProductListingLoader $productListingLoader;

    private SalesChannelContext $salesChannelContext;

    private SystemConfigService $systemConfigService;

    private ?string $productId = null;

    private ?string $mainVariantId = null;

    /**
     * @var array<string>
     */
    private array $optionIds = [];

    /**
     * @var array<string>
     */
    private array $variantIds = [];

    /**
     * @var array<string>
     */
    private array $groupIds = [];

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->productListingLoader = static::getContainer()->get(ProductListingLoader::class);
        $this->salesChannelContext = $this->createSalesChannelContext();
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);

        parent::setUp();
    }

    public function testResolvePreviewEvent(): void
    {
        $ids = new IdsCollection();
        $product = (new ProductBuilder($ids, 'p1'))
            ->price(100)
            ->visibility()
            ->build();
        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->once())->method('__invoke');
        static::getContainer()->get('event_dispatcher')->addListener(ProductListingResolvePreviewEvent::class, $listener);
        $context = static::getContainer()->get(SalesChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $criteria = new Criteria($ids->getList(['p1']));
        $this->productListingLoader->load($criteria, $context);
    }

    public function testMainVariant(): void
    {
        $this->createProduct([], true);

        $listing = $this->fetchListing();

        static::assertSame(1, $listing->getTotal());

        $mainVariant = $listing->getEntities()->first();
        static::assertNotNull($mainVariant);

        static::assertSame($this->mainVariantId, $mainVariant->getId());
        static::assertContains($this->optionIds['red'], $mainVariant->getOptionIds() ?: []);
        static::assertContains($this->optionIds['l'], $mainVariant->getOptionIds() ?: []);
        static::assertTrue($mainVariant->hasExtension('search'));

        static::assertTrue($listing->getCriteria()->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
    }

    public function testMainVariantInactive(): void
    {
        $this->createProduct([], true);

        // update main variant to be inactive
        $this->productRepository->update([[
            'id' => $this->mainVariantId,
            'active' => false,
        ]], $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();

        // another random variant of the product should be displayed
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertNotSame($this->mainVariantId, $variantId);
        static::assertContains($variantId, $this->variantIds);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testMainVariantRemoved(): void
    {
        $this->createProduct([], true);

        $this->productRepository->delete([['id' => $this->mainVariantId]], $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();

        // another random variant of the product should be displayed
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertNotSame($this->mainVariantId, $variantId);
        static::assertContains($variantId, $this->variantIds);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testMainVariantOutOfStock(): void
    {
        $this->createProduct([], true);

        $this->systemConfigService->set(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            true,
            $this->salesChannelContext->getSalesChannelId()
        );

        // update main variant to be out of stock
        $this->productRepository->update([[
            'id' => $this->mainVariantId,
            'stock' => 0,
            'isCloseout' => true,
        ]], $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();

        // another random variant of the product should be displayed
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertNotSame($this->mainVariantId, $variantId);
        static::assertContains($variantId, $this->variantIds);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testChangeProductConfigToSingleVariant(): void
    {
        // no main variant will be set initially
        $this->createProduct(['color', 'size'], false);

        // update product with a main variant
        $this->productRepository->update([[
            'id' => $this->productId,
            'variantListingConfig' => [
                'displayParent' => false,
                'mainVariantId' => $this->mainVariantId,
                'configuratorGroupConfig' => [],
            ],
        ]], $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();

        static::assertSame(1, $listing->getTotal());

        // only main variant should be returned
        $mainVariant = $listing->getEntities()->first();
        static::assertNotNull($mainVariant);

        $optionIds = $mainVariant->getOptionIds();
        static::assertNotNull($optionIds);
        static::assertSame($this->mainVariantId, $mainVariant->getId());
        static::assertContains($this->optionIds['red'], $optionIds);
        static::assertContains($this->optionIds['l'], $optionIds);
        static::assertTrue($mainVariant->hasExtension('search'));
    }

    public function testChangeProductConfigToMainProduct(): void
    {
        // no main variant will be set initially
        $this->createProduct(['color', 'size'], false);

        // update product with a main variant
        $this->productRepository->update([
            [
                'id' => $this->productId,
                'variantListingConfig' => [
                    'displayParent' => true,
                    'mainVariantId' => $this->mainVariantId,
                    'configuratorGroupConfig' => [],
                ],
            ],
        ], $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();

        static::assertSame(1, $listing->getTotal());

        // only main product should be returned
        $mainProduct = $listing->getEntities()->first();
        static::assertNotNull($mainProduct);

        static::assertSame($this->productId, $mainProduct->getId());
        static::assertSame($this->mainVariantId, $mainProduct->getVariantListingConfig()?->getMainVariantId());
        static::assertTrue($mainProduct->hasExtension('search'));
    }

    public function testMainProductIsHiddenIfVariantsOutOfStock(): void
    {
        $this->createProduct([]);

        $this->systemConfigService->set(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            true,
            $this->salesChannelContext->getSalesChannelId()
        );

        $this->productRepository->update([[
            'id' => $this->productId,
            'displayParent' => true,
            'mainVariantId' => $this->mainVariantId,
            'configuratorGroupConfig' => [],
            'isCloseout' => true,
        ]], $this->salesChannelContext->getContext());

        $variants = array_values(\array_map(static fn ($item) => ['id' => $item, 'stock' => 0], $this->variantIds));

        $this->productRepository->update($variants, $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();
        static::assertSame(0, $listing->getTotal());
    }

    public function testMainProductIsHiddenIfAllVariantsDisabled(): void
    {
        $this->createProduct([]);

        $this->productRepository->update([[
            'id' => $this->productId,
            'displayParent' => true,
            'mainVariantId' => $this->mainVariantId,
            'configuratorGroupConfig' => [],
        ]], $this->salesChannelContext->getContext());

        $variants = array_values(\array_map(static fn ($item) => ['id' => $item, 'active' => false], $this->variantIds));

        $this->productRepository->update($variants, $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();
        static::assertSame(0, $listing->getTotal());
    }

    public function testNoListConfig(): void
    {
        $this->createProduct([]);

        $this->productRepository->update([[
            'id' => $this->productId,
            'displayParent' => null,
            'mainVariantId' => null,
            'configuratorGroupConfig' => null,
        ]], $this->salesChannelContext->getContext());

        $firstVariant = $this->fetchListing()->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertContains($variantId, $this->variantIds);
    }

    public function testChangeProductConfigToVariantGroups(): void
    {
        // main variant will be set initially
        $this->createProduct([], true);

        // update product with no main variant
        $this->productRepository->update([[
            'id' => $this->productId,
            'variantListingConfig' => [
                'mainVariantId' => null,
                'configuratorGroupConfig' => $this->getListingConfiguration(['color', 'size']),
            ],
        ]], $this->salesChannelContext->getContext());

        $listing = $this->fetchListing();

        // all variants should be returned
        static::assertSame(4, $listing->getTotal());

        $variants = $listing->getIds();

        static::assertContains($this->variantIds['redXl'], $variants);
        static::assertContains($this->variantIds['redL'], $variants);
        static::assertContains($this->variantIds['greenL'], $variants);
        static::assertContains($this->variantIds['greenXl'], $variants);

        foreach ($listing as $variant) {
            static::assertInstanceOf(ProductEntity::class, $variant);
            static::assertTrue($variant->hasExtension('search'));
        }
    }

    public function testMainVariantAndVariantGroups(): void
    {
        // main variant and variant groups be set initially
        $this->createProduct(['color', 'size'], true);

        $listing = $this->fetchListing();

        // only the main variant should be returned
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertSame($this->mainVariantId, $variantId);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testMainVariantAndVariantGroupsWithFilterOnOptions(): void
    {
        // main variant and variant groups be set initially
        $this->createProduct(['color', 'size'], true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.options.id', $this->optionIds['green']));
        $listing = $this->fetchListing($criteria);

        // only the main variant should be returned
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertSame($this->mainVariantId, $variantId);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testExplicitProductIdKeepsSelectedVariantWhenMainVariantConfigured(): void
    {
        $this->createProduct([], true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->variantIds['greenL']));
        $listing = $this->fetchListing($criteria);

        static::assertSame(1, $listing->getTotal());

        $selectedVariant = $listing->getEntities()->first();
        static::assertNotNull($selectedVariant);
        static::assertSame($this->variantIds['greenL'], $selectedVariant->getId());
        static::assertTrue($selectedVariant->hasExtension('search'));
    }

    public function testExplicitProductIdKeepsSelectedVariantWhenMainProductConfigured(): void
    {
        $this->createProduct(['color', 'size'], false);

        $this->productRepository->update([[
            'id' => $this->productId,
            'variantListingConfig' => [
                'displayParent' => true,
                'mainVariantId' => $this->mainVariantId,
                'configuratorGroupConfig' => $this->getListingConfiguration(['color', 'size']),
            ],
        ]], $this->salesChannelContext->getContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.id', $this->variantIds['greenL']));
        $listing = $this->fetchListing($criteria);

        static::assertSame(1, $listing->getTotal());

        $selectedVariant = $listing->getEntities()->first();
        static::assertNotNull($selectedVariant);
        static::assertSame($this->variantIds['greenL'], $selectedVariant->getId());
        static::assertTrue($selectedVariant->hasExtension('search'));
    }

    public function testExplicitProductIdsReturnAllSelectedVariantsFromSameDisplayGroup(): void
    {
        $this->createProduct([], true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', [$this->variantIds['greenL'], $this->variantIds['greenXl']]));
        $listing = $this->fetchListing($criteria);

        static::assertSame(2, $listing->getTotal());
        static::assertEqualsCanonicalizing([$this->variantIds['greenL'], $this->variantIds['greenXl']], $listing->getIds());
    }

    public function testExplicitProductIdsRespectPaginationWhenMultipleVariantsFromSameDisplayGroupAreSelected(): void
    {
        $this->createProduct([], true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $this->productId));
        $criteria->addFilter(new EqualsAnyFilter('id', [$this->variantIds['greenL'], $this->variantIds['greenXl']]));
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $firstPage = $this->productListingLoader->load(clone $criteria, $this->salesChannelContext);

        static::assertSame(2, $firstPage->getTotal());
        static::assertCount(1, $firstPage->getIds());
        static::assertContains(array_values($firstPage->getIds())[0] ?? null, [$this->variantIds['greenL'], $this->variantIds['greenXl']]);

        $criteria->setOffset(1);
        $secondPage = $this->productListingLoader->load($criteria, $this->salesChannelContext);

        static::assertSame(2, $secondPage->getTotal());
        static::assertCount(1, $secondPage->getIds());
        static::assertEqualsCanonicalizing(
            [$this->variantIds['greenL'], $this->variantIds['greenXl']],
            [...$firstPage->getIds(), ...$secondPage->getIds()]
        );
    }

    public function testExplicitProductIdsMixedWithOtherConditionsKeepPaginationStable(): void
    {
        $this->createProduct([], true);
        $this->createAdditionalStockFilteredFamilies(50);

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsAnyFilter('id', [
                $this->variantIds['greenXl'],
                $this->variantIds['redL'],
                $this->variantIds['greenL'],
            ]),
            new RangeFilter('stock', [RangeFilter::GT => 49]),
        ]));
        $criteria->addSorting(new FieldSorting('productNumber', FieldSorting::ASCENDING));
        $criteria->setLimit(24);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $firstPage = $this->productListingLoader->load(clone $criteria, $this->salesChannelContext);

        $criteria->setOffset(24);
        $secondPage = $this->productListingLoader->load(clone $criteria, $this->salesChannelContext);

        $criteria->setOffset(48);
        $thirdPage = $this->productListingLoader->load($criteria, $this->salesChannelContext);

        static::assertGreaterThan(48, $firstPage->getTotal());
        static::assertSame($firstPage->getTotal(), $secondPage->getTotal());
        static::assertSame($firstPage->getTotal(), $thirdPage->getTotal());
        static::assertCount(24, $firstPage->getIds());
        static::assertCount(24, $secondPage->getIds());
        static::assertCount($firstPage->getTotal() - 48, $thirdPage->getIds());
        static::assertSame([
            $this->variantIds['greenXl'],
            $this->variantIds['redL'],
            $this->variantIds['greenL'],
        ], \array_slice(array_values($firstPage->getIds()), 0, 3));
        static::assertSame([], array_values(array_intersect($firstPage->getIds(), $secondPage->getIds())));
        static::assertSame([], array_values(array_intersect($firstPage->getIds(), $thirdPage->getIds())));
        static::assertSame([], array_values(array_intersect($secondPage->getIds(), $thirdPage->getIds())));
        static::assertCount($firstPage->getTotal(), array_unique([
            ...$firstPage->getIds(),
            ...$secondPage->getIds(),
            ...$thirdPage->getIds(),
        ]));
    }

    public function testExplicitProductIdsWithAndFilterKeepMatchingSelection(): void
    {
        $this->createProduct(['color', 'size'], true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $this->productId));
        $criteria->addFilter(new EqualsAnyFilter('product.id', [$this->variantIds['greenL'], $this->variantIds['greenXl']]));
        $criteria->addFilter(new EqualsFilter('product.options.id', $this->optionIds['green']));
        $listing = $this->productListingLoader->load($criteria, $this->salesChannelContext);

        static::assertSame(2, $listing->getTotal());
        static::assertEqualsCanonicalizing([$this->variantIds['greenL'], $this->variantIds['greenXl']], $listing->getIds());
    }

    public function testExplicitProductIdsWithAndFilterReturnIntersectionOnly(): void
    {
        $this->createProduct(['color', 'size'], true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $this->productId));
        $criteria->addFilter(new EqualsAnyFilter('product.id', [$this->variantIds['greenL'], $this->variantIds['greenXl']]));
        $criteria->addFilter(new EqualsFilter('product.options.id', $this->optionIds['xl']));
        $listing = $this->productListingLoader->load($criteria, $this->salesChannelContext);

        static::assertSame(1, $listing->getTotal());
        static::assertSame([$this->variantIds['greenXl']], array_values($listing->getIds()));
    }

    public function testMainVariantAndVariantGroupsWithPostFilterOnOptions(): void
    {
        // main variant and variant groups be set initially
        $this->createProduct(['color', 'size'], true);

        $criteria = new Criteria();
        $criteria->addPostFilter(new EqualsFilter('product.options.id', $this->optionIds['green']));
        $listing = $this->fetchListing($criteria);

        // only one of the green variants should be returned
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertSame($this->mainVariantId, $variantId);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testMainProductWithPostFilterOnOptionsKeepsParentProduct(): void
    {
        $this->createProduct(['color', 'size'], false);

        $this->productRepository->update([[
            'id' => $this->productId,
            'variantListingConfig' => [
                'displayParent' => true,
                'mainVariantId' => $this->mainVariantId,
                'configuratorGroupConfig' => $this->getListingConfiguration(['color', 'size']),
            ],
        ]], $this->salesChannelContext->getContext());

        $criteria = new Criteria();
        $criteria->addPostFilter(new EqualsFilter('product.options.id', $this->optionIds['green']));
        $listing = $this->fetchListing($criteria);

        static::assertSame(1, $listing->getTotal());

        $mainProduct = $listing->getEntities()->first();
        static::assertNotNull($mainProduct);
        static::assertSame($this->productId, $mainProduct->getId());
        static::assertTrue($mainProduct->hasExtension('search'));
    }

    public static function searchStatesProvider(): \Generator
    {
        yield [ResolvedCriteriaProductSearchRoute::STATE];
        yield [ProductSuggestRoute::STATE];
    }

    #[DataProvider('searchStatesProvider')]
    public function testVariantOnSearchResult(string $state): void
    {
        $this->createProduct([], true);

        $criteria = new Criteria();
        $criteria->addState($state);

        $this->systemConfigService->set(
            'core.listing.findBestVariant',
            true,
            $this->salesChannelContext->getSalesChannelId()
        );

        $listing = $this->fetchListing($criteria, 'greenL');

        // only the main variant should be returned
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertNotSame($this->variantIds['greenL'], $this->mainVariantId);
        static::assertSame($this->variantIds['greenL'], $variantId);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testLoadPreviewsOnSearchPage(): void
    {
        $this->systemConfigService->set(
            'core.listing.findBestVariant',
            false,
            $this->salesChannelContext->getSalesChannelId()
        );

        // no main variant will be set initially
        $this->createProduct(['color', 'size']);

        // update product with a main variant
        $this->productRepository->update([
            [
                'id' => $this->productId,
                'variantListingConfig' => [
                    'displayParent' => true,
                    'mainVariantId' => $this->mainVariantId,
                    'configuratorGroupConfig' => [],
                ],
            ],
        ], $this->salesChannelContext->getContext());

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaProductSearchRoute::STATE);
        $listing = $this->fetchListing($criteria, 'greenL');

        $foundProduct = $listing->getEntities()->first();

        static::assertSame(1, $listing->getTotal());

        static::assertInstanceOf(SalesChannelProductEntity::class, $foundProduct);
        static::assertSame($this->productId, $foundProduct->getId());
        static::assertSame($this->mainVariantId, $foundProduct->getVariantListingConfig()?->getMainVariantId());
        static::assertTrue($foundProduct->hasExtension('search'));

        $this->systemConfigService->set(
            'core.listing.findBestVariant',
            true,
            $this->salesChannelContext->getSalesChannelId()
        );

        $listing = $this->fetchListing($criteria, 'greenL');

        static::assertSame(1, $listing->getTotal());

        $foundProduct = $listing->getEntities()->first();

        static::assertInstanceOf(SalesChannelProductEntity::class, $foundProduct);
        static::assertSame($this->variantIds['greenL'], $foundProduct->getId());
        static::assertSame($this->mainVariantId, $foundProduct->getVariantListingConfig()?->getMainVariantId());
        static::assertTrue($foundProduct->hasExtension('search'));
    }

    public function testAllVariants(): void
    {
        $this->createProduct(['size', 'color'], false);

        $listing = $this->fetchListing();

        // all variants should be returned
        static::assertSame(4, $listing->getTotal());

        $variants = $listing->getIds();

        static::assertContains($this->variantIds['redXl'], $variants);
        static::assertContains($this->variantIds['redL'], $variants);
        static::assertContains($this->variantIds['greenL'], $variants);
        static::assertContains($this->variantIds['greenXl'], $variants);

        foreach ($listing as $variant) {
            static::assertTrue($variant->hasExtension('search'));
        }
    }

    public function testMainVariantHasScoreInSearchExtension(): void
    {
        $this->createProduct([], true);

        $listing = $this->fetchListing();

        static::assertSame(1, $listing->getTotal());

        $mainVariant = $listing->getEntities()->first();
        static::assertNotNull($mainVariant);

        static::assertSame($this->mainVariantId, $mainVariant->getId());
        static::assertContains($this->optionIds['red'], $mainVariant->getOptionIds() ?: []);
        static::assertContains($this->optionIds['l'], $mainVariant->getOptionIds() ?: []);
        static::assertTrue($mainVariant->hasExtension('search'));

        $searchData = $mainVariant->get('search');
        static::assertInstanceOf(ArrayEntity::class, $searchData);
        static::assertTrue($searchData->get('_score') > 0);
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    private function fetchListing(?Criteria $criteria = null, string $term = 'example'): EntitySearchResult
    {
        if (!$criteria instanceof Criteria) {
            $criteria = new Criteria();
        }

        $criteria->addFilter(new EqualsFilter('product.parentId', $this->productId));
        $criteria->setTerm($term);

        return $this->productListingLoader->load($criteria, $this->salesChannelContext);
    }

    private function createAdditionalStockFilteredFamilies(int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $productId = Uuid::randomHex();

            $optionIds = [
                'red' => Uuid::randomHex(),
                'green' => Uuid::randomHex(),
                'xl' => Uuid::randomHex(),
                'l' => Uuid::randomHex(),
            ];

            $variantIds = [
                'redXl' => Uuid::randomHex(),
                'greenXl' => Uuid::randomHex(),
                'redL' => Uuid::randomHex(),
                'greenL' => Uuid::randomHex(),
            ];

            $groupIds = [
                'color' => Uuid::randomHex(),
                'size' => Uuid::randomHex(),
            ];

            $mainVariantId = $variantIds['redL'];
            $tax = ['id' => Uuid::randomHex(), 'name' => '19', 'taxRate' => 19];

            $data = [
                [
                    'id' => $productId,
                    'variantListingConfig' => [
                        'displayParent' => null,
                        'mainVariantId' => null,
                        'configuratorGroupConfig' => [],
                    ],
                    'productNumber' => \sprintf('mixed-%03d-parent', $i),
                    'manufacturer' => ['name' => \sprintf('mixed-%03d-manufacturer', $i)],
                    'tax' => $tax,
                    'stock' => 10,
                    'name' => \sprintf('mixed family %03d', $i),
                    'active' => true,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true],
                    ],
                    'configuratorSettings' => [
                        [
                            'option' => [
                                'id' => $optionIds['red'],
                                'name' => 'Red',
                                'group' => [
                                    'id' => $groupIds['color'],
                                    'name' => 'Color',
                                ],
                            ],
                        ],
                        [
                            'option' => [
                                'id' => $optionIds['green'],
                                'name' => 'Green',
                                'group' => [
                                    'id' => $groupIds['color'],
                                    'name' => 'Color',
                                ],
                            ],
                        ],
                        [
                            'option' => [
                                'id' => $optionIds['xl'],
                                'name' => 'XL',
                                'group' => [
                                    'id' => $groupIds['size'],
                                    'name' => 'size',
                                ],
                            ],
                        ],
                        [
                            'option' => [
                                'id' => $optionIds['l'],
                                'name' => 'L',
                                'group' => [
                                    'id' => $groupIds['size'],
                                    'name' => 'size',
                                ],
                            ],
                        ],
                    ],
                    'visibilities' => [
                        [
                            'salesChannelId' => $this->salesChannelContext->getSalesChannelId(),
                            'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                        ],
                    ],
                ],
                [
                    'id' => $variantIds['redXl'],
                    'productNumber' => \sprintf('mixed-%03d-red-xl', $i),
                    'stock' => 60,
                    'name' => \sprintf('mixed red xl %03d', $i),
                    'active' => true,
                    'parentId' => $productId,
                    'options' => [
                        ['id' => $optionIds['red']],
                        ['id' => $optionIds['xl']],
                    ],
                ],
                [
                    'id' => $variantIds['greenXl'],
                    'productNumber' => \sprintf('mixed-%03d-green-xl', $i),
                    'stock' => 60,
                    'name' => \sprintf('mixed green xl %03d', $i),
                    'active' => true,
                    'parentId' => $productId,
                    'options' => [
                        ['id' => $optionIds['green']],
                        ['id' => $optionIds['xl']],
                    ],
                ],
                [
                    'id' => $variantIds['redL'],
                    'productNumber' => \sprintf('mixed-%03d-red-l', $i),
                    'stock' => 60,
                    'name' => \sprintf('mixed red l %03d', $i),
                    'active' => true,
                    'parentId' => $productId,
                    'options' => [
                        ['id' => $optionIds['red']],
                        ['id' => $optionIds['l']],
                    ],
                ],
                [
                    'id' => $variantIds['greenL'],
                    'productNumber' => \sprintf('mixed-%03d-green-l', $i),
                    'stock' => 60,
                    'name' => \sprintf('mixed green l %03d', $i),
                    'active' => true,
                    'parentId' => $productId,
                    'options' => [
                        ['id' => $optionIds['green']],
                        ['id' => $optionIds['l']],
                    ],
                ],
            ];

            $this->addTaxDataToSalesChannel($this->salesChannelContext, $tax);
            $this->productRepository->create($data, $this->salesChannelContext->getContext());

            $this->productRepository->update([
                [
                    'id' => $productId,
                    'variantListingConfig' => [
                        'displayParent' => null,
                        'mainVariantId' => $mainVariantId,
                        'configuratorGroupConfig' => [],
                    ],
                ],
            ], $this->salesChannelContext->getContext());
        }
    }

    /**
     * @param array<string> $listingProperties
     */
    private function createProduct(array $listingProperties, bool $hasMainVariant = false): void
    {
        $this->productId = Uuid::randomHex();

        $this->optionIds = [
            'red' => Uuid::randomHex(),
            'green' => Uuid::randomHex(),
            'xl' => Uuid::randomHex(),
            'l' => Uuid::randomHex(),
        ];

        $this->variantIds = [
            'redXl' => Uuid::randomHex(),
            'greenXl' => Uuid::randomHex(),
            'redL' => Uuid::randomHex(),
            'greenL' => Uuid::randomHex(),
        ];

        $this->variantIds['mainVariantId'] = $this->variantIds['redL'];

        $this->groupIds = [
            'color' => Uuid::randomHex(),
            'size' => Uuid::randomHex(),
        ];

        $this->mainVariantId = $this->variantIds['redL'];

        $config = $this->getListingConfiguration($listingProperties);

        $tax = ['id' => Uuid::randomHex(), 'name' => '19', 'taxRate' => 19];

        $data = [
            [
                'id' => $this->productId,
                'variantListingConfig' => [
                    'displayParent' => null,
                    'mainVariantId' => null,
                    'configuratorGroupConfig' => $config,
                ],
                'productNumber' => 'a.0',
                'manufacturer' => ['name' => 'test'],
                'tax' => $tax,
                'stock' => 10,
                'name' => 'example',
                'active' => true,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true],
                ],
                'configuratorSettings' => [
                    [
                        'option' => [
                            'id' => $this->optionIds['red'],
                            'name' => 'Red',
                            'group' => [
                                'id' => $this->groupIds['color'],
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['green'],
                            'name' => 'Green',
                            'group' => [
                                'id' => $this->groupIds['color'],
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['xl'],
                            'name' => 'XL',
                            'group' => [
                                'id' => $this->groupIds['size'],
                                'name' => 'size',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['l'],
                            'name' => 'L',
                            'group' => [
                                'id' => $this->groupIds['size'],
                                'name' => 'size',
                            ],
                        ],
                    ],
                ],
                'visibilities' => [
                    [
                        'salesChannelId' => $this->salesChannelContext->getSalesChannelId(),
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $this->variantIds['redXl'],
                'productNumber' => 'a.1',
                'stock' => 10,
                'name' => 'example redXl',
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['xl']],
                ],
            ],
            [
                'id' => $this->variantIds['greenXl'],
                'productNumber' => 'a.3',
                'stock' => 10,
                'name' => 'example greenXl',
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['xl']],
                ],
            ],
            [
                'id' => $this->variantIds['redL'],
                'productNumber' => 'a.5',
                'stock' => 10,
                'name' => 'example redL',
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['l']],
                ],
            ],
            [
                'id' => $this->variantIds['greenL'],
                'productNumber' => 'a.7',
                'stock' => 10,
                'name' => 'example greenL',
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['l']],
                ],
            ],
        ];

        $this->addTaxDataToSalesChannel($this->salesChannelContext, $tax);

        $this->productRepository->create($data, $this->salesChannelContext->getContext());

        if ($hasMainVariant) {
            // Update parent product, configure to use selected mainVariantId in listing config
            $this->productRepository->update([
                [
                    'id' => $this->productId,
                    'variantListingConfig' => [
                        'displayParent' => null,
                        'mainVariantId' => $this->mainVariantId,
                        'configuratorGroupConfig' => $config,
                    ],
                ],
            ], $this->salesChannelContext->getContext());
        }
    }

    /**
     * @param array<string> $listingProperties
     *
     * @return array<int, array<string, string|true>>
     */
    private function getListingConfiguration(array $listingProperties): array
    {
        $config = [];

        foreach ($listingProperties as $groupName) {
            $config[] = [
                'id' => $this->groupIds[$groupName],
                'expressionForListings' => true,
                'representation' => 'box', // box, select, image, color
            ];
        }

        return $config;
    }
}
