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
use Shopware\Core\Content\Product\Events\ProductSliderStaticCriteriaEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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

    private EventDispatcherInterface&MockObject $eventDispatcher;

    protected function setUp(): void
    {
        $this->config = new FieldConfigCollection();
        $this->configService = $this->createMock(SystemConfigService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
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

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(ProductSliderStaticCriteriaEvent::class));

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

    public function testCollectEventCanModifyCriteria(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_STATIC, ['product-1']);
        $this->config->add($config);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (ProductSliderStaticCriteriaEvent $event): ProductSliderStaticCriteriaEvent {
                $event->criteria->addAssociation('manufacturer');

                return $event;
            });

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        $list = array_shift($list);
        $criteria = $list['product-slider_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertTrue($criteria->hasAssociation('manufacturer'));
    }

    public function testEnrichWithAvailableProducts(): void
    {
        $this->hideUnavailableProducts(false);

        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, ['product-1', 'product-2']));
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

    public function testEnrichRestoresConfiguredProductOrder(): void
    {
        $this->hideUnavailableProducts(false);

        // Configure the slot with products in order [product-2, product-1]
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, ['product-2', 'product-1']));
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        // Simulate the DB returning products in a different order (e.g. as merged from another slider)
        $products = $this->getProducts(); // returns [product-1, product-2]
        $searchResult = $this->getEntitySearchResult($products);
        $searchResult->assign(['criteria' => new Criteria(['product-1', 'product-2', 'product-2', 'product-1'])]);

        $data = new ElementDataCollection();
        $data->add('product-slider_id', $searchResult);

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $enrichedData = $slot->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $enrichedData);

        $enrichedProducts = $enrichedData->getProducts();
        static::assertInstanceOf(ProductCollection::class, $enrichedProducts);
        static::assertCount(2, $enrichedProducts);

        $ids = array_values($enrichedProducts->getIds());
        static::assertSame(['product-2', 'product-1'], $ids, 'Products must be in the configured slider order, not DB order');
    }

    public function testEnrichHideUnavailableProducts(): void
    {
        $this->hideUnavailableProducts(true);

        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, ['product-1', 'product-2']));
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
        return new StaticProductProcessor($this->configService, $this->eventDispatcher);
    }

    private function hideUnavailableProducts(bool $value): void
    {
        $this->configService->expects($this->once())->method('getBool')->willReturn($value);
    }
}
