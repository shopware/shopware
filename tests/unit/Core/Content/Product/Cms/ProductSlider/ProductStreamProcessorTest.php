<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms\ProductSlider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\Cms\ProductSlider\ProductStreamProcessor;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\Tax\TaxCollection;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductStreamProcessor::class)]
class ProductStreamProcessorTest extends TestCase
{
    use ProductSliderUnitTrait;

    protected FieldConfigCollection $config;

    private ProductStreamBuilderInterface&MockObject $productStreamBuilder;

    private SalesChannelRepository&MockObject $productRepository;

    protected function setUp(): void
    {
        $this->productStreamBuilder = $this->createMock(ProductStreamBuilderInterface::class);
        $this->productStreamBuilder->method('buildFilters')->willReturn([$this->getFilter()]);

        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->config = new FieldConfigCollection();
    }

    public function testGetDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->getProcessor()->getDecorated();
    }

    public function testGetSource(): void
    {
        static::assertSame('product_stream', $this->getProcessor()->getSource());
    }

    public function testCollect(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');

        $this->config->add($config);

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        static::assertCount(1, $list);

        $criteria = $list[ProductDefinition::class]['product-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $filters = $criteria->getFilters();
        static::assertCount(2, $filters);

        $filter = array_shift($filters);
        static::assertEquals($this->getFilter(), $filter);

        $filter = array_shift($filters);
        $groupingFilter = new NotFilter(
            NotFilter::CONNECTION_AND,
            [new EqualsFilter('displayGroup', null)]
        );

        static::assertEquals($groupingFilter, $filter);
    }

    public function testCollectAddsRandomSortingIfRequired(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $productsConfig = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $sortingConfig = new FieldConfig('productStreamSorting', FieldConfig::SOURCE_PRODUCT_STREAM, 'random');

        $this->config->add($productsConfig);
        $this->config->add($sortingConfig);

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        static::assertCount(1, $list);

        $criteria = $list[ProductDefinition::class]['product-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $sorting = $criteria->getSorting();
        static::assertCount(2, $sorting);
        static::assertContainsOnlyInstancesOf(FieldSorting::class, $sorting);
    }

    public function testEnrich(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $this->config->add($config);

        $products = $this->getProducts();
        $searchResult = $this->getEntitySearchResult($products);

        $data = new ElementDataCollection();
        $data->add('product-slider-entity-fallback_id', $searchResult);

        $this->productRepository->expects(static::once())
            ->method('search')->willReturn($searchResult);

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $slider);
        static::assertSame('product-stream-1', $slider->getStreamId());
        static::assertEquals($products, $slider->getProducts());
    }

    public function testEnrichDoesNothingWithoutEntitySearchResult(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();
        $data = new ElementDataCollection();

        $this->getProcessor()->enrich($slot, $data, $resolverContext);
        static::assertNull($slot->getData());
    }

    public function testEnrichDoesNothingWithoutProducts(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();
        $data = new ElementDataCollection();

        $result = new EntitySearchResult(
            'tax',
            2,
            new TaxCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $data->add('product-slider-entity-fallback_id', $result);
        $this->getProcessor()->enrich($slot, $data, $resolverContext);
        static::assertNull($slot->getData());
    }

    public function testEnrichUsesEmptyProductCollectionIfNoProductIdsDetermined(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();
        $data = new ElementDataCollection();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $this->config->add($config);

        $result = new EntitySearchResult(
            'product',
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $data->add('product-slider-entity-fallback_id', $result);

        $this->productRepository->expects(static::never())
            ->method('search');

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $slider);
        static::assertEmpty($slider->getProducts());
    }

    private function getProcessor(): ProductStreamProcessor
    {
        return new ProductStreamProcessor($this->productStreamBuilder, $this->productRepository);
    }

    private function getFilter(): MultiFilter
    {
        return new MultiFilter(MultiFilter::CONNECTION_OR, [
            new ContainsFilter('product.name', 'Awesome'),
            new EqualsFilter('product.id', 'product-1'),
        ]);
    }
}
