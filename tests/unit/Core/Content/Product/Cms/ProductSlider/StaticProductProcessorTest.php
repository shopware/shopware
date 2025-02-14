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
use Shopware\Core\Content\Product\Cms\ProductSlider\StaticProductProcessor;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StaticProductProcessor::class)]
class StaticProductProcessorTest extends TestCase
{
    use ProductSliderUnitTrait;

    protected FieldConfigCollection $config;

    private SystemConfigService&MockObject $configService;

    protected function setUp(): void
    {
        $this->config = new FieldConfigCollection();
        $this->configService = $this->createMock(SystemConfigService::class);
    }

    public function testGetDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->getProcessor()->getDecorated();
    }

    public function testGetSource(): void
    {
        static::assertSame('static', $this->getProcessor()->getSource());
    }

    public function testCollect(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $expectedIds = ['product-1', 'product-2'];

        $config = new FieldConfig('products', FieldConfig::SOURCE_STATIC, $expectedIds);
        $this->config->add($config);

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        static::assertCount(1, $list);

        $list = array_shift($list);
        $criteria = $list['product-slider_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $ids = $criteria->getIds();
        static::assertSame($expectedIds, $ids);
    }

    public function testEnrichWithAvailableProducts(): void
    {
        $this->hideUnavailableProducts(false);

        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $products = $this->getProducts();
        $searchResult = $this->getEntitySearchResult($products);

        $data = new ElementDataCollection();
        $data->add('product-slider_id', $searchResult);

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $data = $slot->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $data);

        $products = $data->getProducts();
        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(2, $products);
        static::assertTrue($products->has('product-1'));
        static::assertTrue($products->has('product-2'));
    }

    public function testEnrichHideUnavailableProducts(): void
    {
        $this->hideUnavailableProducts(true);

        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $products = $this->getProducts();
        $searchResult = $this->getEntitySearchResult($products);

        $data = new ElementDataCollection();
        $data->add('product-slider_id', $searchResult);

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $data = $slot->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $data);

        $products = $data->getProducts();
        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(1, $products);
        static::assertTrue($products->has('product-1'));
        static::assertFalse($products->has('product-2'));
    }

    public function testEnrichDoesNothingWithoutSearchResult(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();
        $data = new ElementDataCollection();

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $data = $slot->getData();
        static::assertNull($data);
    }

    public function testEnrichDoesNothingWithoutProducts(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $searchResult = new EntitySearchResult(
            'tax',
            2,
            new EntityCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $data = new ElementDataCollection();
        $data->add('product-slider_id', $searchResult);

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $data = $slot->getData();
        static::assertNull($data);
    }

    private function getProcessor(): StaticProductProcessor
    {
        return new StaticProductProcessor($this->configService);
    }

    private function hideUnavailableProducts(bool $value): void
    {
        $this->configService->expects(static::once())->method('get')->willReturn($value);
    }
}
