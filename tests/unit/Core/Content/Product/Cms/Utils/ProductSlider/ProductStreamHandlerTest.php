<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms\Utils\ProductSlider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\Cms\Utils\ProductSlider\ProductStreamHandler;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductStreamHandler::class)]
class ProductStreamHandlerTest extends TestCase
{
    use ProductSliderUnitTrait;

    private ProductStreamBuilderInterface&MockObject $productStreamBuilder;

    private SalesChannelRepository&MockObject $productRepository;

    private FieldConfigCollection $config;

    protected function setUp(): void
    {
        $this->productStreamBuilder = $this->createMock(ProductStreamBuilderInterface::class);
        $this->productStreamBuilder->method('buildFilters')->willReturn([$this->getFilter()]);

        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->config = new FieldConfigCollection();
    }

    public function testGetSource(): void
    {
        static::assertSame('product_stream', $this->getHandler()->getSource());
    }

    public function testCollect(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');

        $this->config->add($config);

        $collection = $this->getHandler()->collect($slot, $this->config, $resolverContext);
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

        $this->getHandler()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $slider);
        static::assertSame('product-stream-1', $slider->getStreamId());
        static::assertEquals($products, $slider->getProducts());
    }

    private function getHandler(): ProductStreamHandler
    {
        return new ProductStreamHandler($this->productStreamBuilder, $this->productRepository);
    }

    private function getFilter(): MultiFilter
    {
        return new MultiFilter(MultiFilter::CONNECTION_OR, [
            new ContainsFilter('product.name', 'Awesome'),
            new EqualsFilter('product.id', 'product-1'),
        ]);
    }
}
