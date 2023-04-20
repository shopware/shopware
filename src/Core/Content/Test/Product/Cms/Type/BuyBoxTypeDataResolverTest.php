<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\BuyBoxStruct;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cms\BuyBoxCmsElementResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BuyBoxTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private BuyBoxCmsElementResolver $buyBoxResolver;

    protected function setUp(): void
    {
        $saleChannelProductEntity = new SalesChannelProductEntity();
        $saleChannelProductEntity->setId('product123');
        $mockConfiguratorLoader = $this->createMock(ProductConfiguratorLoader::class);
        $mockConfiguratorLoader->method('load')->willReturn(
            new PropertyGroupCollection()
        );

        $repositoryMock = $this->createMock(EntityRepository::class);

        $this->buyBoxResolver = new BuyBoxCmsElementResolver($mockConfiguratorLoader, $repositoryMock);
    }

    public function testGetType(): void
    {
        static::assertSame('buy-box', $this->buyBoxResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->buyBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->buyBoxResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, $criteriaCollection->all());
        /** @var Criteria $criteria */
        $criteria = $criteriaCollection->all()[SalesChannelProductDefinition::class]['product_id'];

        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertCount(1, $criteria->getFilters());
        /** @var OrFilter $orFilter */
        static::assertInstanceOf(OrFilter::class, $orFilter = $criteria->getFilters()[0]);
        static::assertCount(2, $queries = $orFilter->getQueries());

        static::assertInstanceOf(EqualsFilter::class, $firstQuery = $queries[0]);
        static::assertEquals('product.parentId', $firstQuery->getField());
        static::assertEquals('product123', $firstQuery->getValue());
        static::assertInstanceOf(EqualsFilter::class, $secondQuery = $queries[1]);
        static::assertEquals('id', $secondQuery->getField());
        static::assertEquals('product123', $secondQuery->getValue());
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->buyBoxResolver->enrich($slot, $resolverContext, $result);

        /** @var BuyBoxStruct|null $buyBoxStruct */
        $buyBoxStruct = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $buyBoxStruct);
        static::assertNull($buyBoxStruct->getProductId());
        static::assertNull($buyBoxStruct->getProduct());
    }

    public function testEnrichWithStaticConfig(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setId('product123');

        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();
        $result->add('product_id', new EntitySearchResult(
            'product',
            1,
            new ProductCollection([$product]),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, 'product123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $this->buyBoxResolver->enrich($slot, $resolverContext, $result);

        /** @var BuyBoxStruct|null $buyBoxStruct */
        $buyBoxStruct = $slot->getData();
        static::assertInstanceOf(BuyBoxStruct::class, $buyBoxStruct);
        static::assertSame($product->getId(), $buyBoxStruct->getProductId());
    }

    public function testCollectWithEmptyProductId(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->buyBoxResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testReviewCountLoaded(): void
    {
        $productId = Uuid::randomHex();
        $saleChannelContext = $this->createSalesChannelContext();

        $this->createProduct($productId);
        $this->createReviews($productId);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_STATIC, $productId));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        $resolverContext = new ResolverContext($saleChannelContext, new Request());

        $result = new ElementDataCollection();
        $result->add('product_id', new EntitySearchResult(
            'product',
            1,
            new ProductCollection([$product]),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $buyBoxResolver = $this->getContainer()->get(BuyBoxCmsElementResolver::class);
        $buyBoxResolver->enrich($slot, $resolverContext, $result);

        /** @var BuyBoxStruct|null $buyBoxStruct */
        $buyBoxStruct = $slot->getData();

        static::assertSame(3, $buyBoxStruct->getTotalReviews());
    }

    /**
     * @dataProvider reviewCountDataProvider
     */
    public function testReviewCountLoadedWithVariants(int $variantCount, int $reviewsPerProduct, int $expectedReviews): void
    {
        $productId = Uuid::randomHex();
        $variantIds = [];
        $salesChannelContext = $this->createSalesChannelContext();

        $this->createProduct($productId);
        $this->createReviews($productId, $reviewsPerProduct);
        for ($i = 0; $i < $variantCount; ++$i) {
            $variantIds[$i] = Uuid::randomHex();
            $this->createProduct($variantIds[$i], ['parentId' => $productId]);
            $this->createReviews($variantIds[$i], $reviewsPerProduct);
        }
        $investigatedProductId = $variantIds[0] ?? $productId;

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('product', FieldConfig::SOURCE_MAPPED, $investigatedProductId));
        $fieldConfig->add(new FieldConfig('alignment', FieldConfig::SOURCE_STATIC, null));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('buy-box');
        $slot->setFieldConfig($fieldConfig);

        $variantProduct = new SalesChannelProductEntity();
        $variantProduct->setId($investigatedProductId);
        if (isset($variantIds[0])) {
            $variantProduct->setParentId($productId);
        }
        $variantProduct->setOptionIds([Uuid::randomHex()]);

        $resolverContext = new EntityResolverContext(
            $salesChannelContext,
            new Request(),
            $this->createMock(SalesChannelProductDefinition::class),
            $variantProduct
        );

        $result = new ElementDataCollection();

        $buyBoxResolver = $this->getContainer()->get(BuyBoxCmsElementResolver::class);
        $buyBoxResolver->enrich($slot, $resolverContext, $result);

        /** @var BuyBoxStruct|null $buyBoxStruct */
        $buyBoxStruct = $slot->getData();

        static::assertSame($expectedReviews, $buyBoxStruct->getTotalReviews());
    }

    public static function reviewCountDataProvider(): iterable
    {
        // variant count, reviews per variant, expected review count
        yield 'No variants, No reviews' => [0, 0, 0];
        yield 'No variants, 3 reviews' => [0, 3, 3];
        yield 'No variants, 10 reviews' => [0, 10, 10];

        yield 'One variant, No reviews' => [1, 0, 0];
        yield 'One variant, 3 reviews each' => [1, 3, 6];
        yield 'One variant, 10 reviews each' => [1, 10, 20];

        yield '5 variants, No reviews' => [5, 0, 0];
        yield '5 variants, 3 reviews each' => [5, 3, 18];
        yield '5 variants, 10 reviews each' => [5, 10, 60];
    }

    private function createProduct(string $productId, array $additionalData = []): void
    {
        $data = array_merge([
            'id' => $productId,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'purchasePrice' => 7.5,
            'purchasePrices' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 7.5, 'net' => 5, 'linked' => false],
                ['currencyId' => Uuid::randomHex(), 'gross' => 150, 'net' => 100, 'linked' => false],
            ],
            'active' => true,
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'weight' => 100,
            'height' => 101,
            'width' => 102,
            'length' => 103,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test',
                ],
            ],
        ], $additionalData);

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function createReviews(string $productId, int $reviewCount = 3): void
    {
        $reviews = [];
        for ($i = 1; $i <= $reviewCount; ++$i) {
            $reviews[] = [
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'productId' => $productId,
                'title' => 'Test',
                'content' => 'test',
                'points' => min(5, $i + $i / 5),
                'status' => true,
            ];
        }

        $this->getContainer()->get('product_review.repository')
            ->create($reviews, Context::createDefaultContext());
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }
}
