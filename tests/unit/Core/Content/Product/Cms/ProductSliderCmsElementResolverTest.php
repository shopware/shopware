<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Product\Cms\ProductSlider\AbstractProductSliderProcessor;
use Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Product\Cms\ProductSlider\ProductSliderUnitTrait;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductSliderCmsElementResolver::class)]
class ProductSliderCmsElementResolverTest extends TestCase
{
    use ProductSliderUnitTrait;

    protected FieldConfigCollection $config;

    private AbstractProductSliderProcessor&MockObject $processor;

    private LoggerInterface&MockObject $logger;

    /**
     * @var AbstractProductSliderProcessor[]
     */
    private array $processors = [];

    protected function setUp(): void
    {
        $this->config = new FieldConfigCollection();
        $this->processor = $this->createMock(AbstractProductSliderProcessor::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testGetType(): void
    {
        static::assertSame('product-slider', $this->getResolver()->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, null));

        $slot = $this->getSlot();
        $collection = $this->getResolver()->collect($slot, $this->getResolverContext());

        static::assertNull($collection);
    }

    public function testCollectNoProcessorFound(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $this->logger->expects(static::once())->method('error')
            ->with('No product slider processor found by provided source: "static"');

        $this->processor->expects(static::once())->method('getSource')->willReturn('not-existing-processor');
        $this->processors[] = $this->processor;

        $slot = $this->getSlot();
        $collection = $this->getResolver()->collect($slot, $this->getResolverContext());
        static::assertNull($collection);
    }

    public function testCollect(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $collection = new CriteriaCollection();
        $collection->add('product', ProductDefinition::class, new Criteria());

        $this->processor->method('getSource')->willReturn(FieldConfig::SOURCE_STATIC);
        $this->processor->expects(static::once())
            ->method('collect')
            ->willReturn($collection);

        $this->processors['static'] = $this->processor;

        $slot = $this->getSlot();
        static::assertSame($collection, $this->getResolver()->collect($slot, $this->getResolverContext()));
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, null));

        $slot = $this->getSlot();
        $data = new ElementDataCollection();

        $processor = $this->createMock(AbstractProductSliderProcessor::class);
        $processor->expects(static::never())->method('enrich');

        $this->getResolver()->enrich($slot, $this->getResolverContext(), $data);
    }

    public function testEnrichNoProcessorFound(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $this->logger->expects(static::once())->method('error')
            ->with('No product slider processor found by provided source: "static"');

        $this->processor->expects(static::once())->method('getSource')->willReturn('not-existing-processor');
        $this->processor->expects(static::never())->method('enrich');
        $this->processors[] = $this->processor;

        $slot = $this->getSlot();
        $data = new ElementDataCollection();

        $this->getResolver()->enrich($slot, $this->getResolverContext(), $data);
    }

    public function testEnrich(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $slot = $this->getSlot();
        $data = new ElementDataCollection();
        $resolverContext = $this->getResolverContext();

        $processor = $this->createMock(AbstractProductSliderProcessor::class);
        $processor->method('getSource')->willReturn(FieldConfig::SOURCE_STATIC);
        $processor->expects(static::once())->method('enrich')->with($slot, $data, $resolverContext);

        $this->processors['static'] = $processor;

        $this->getResolver()->enrich($slot, $this->getResolverContext(), $data);
    }

    private function getResolver(): ProductSliderCmsElementResolver
    {
        return new ProductSliderCmsElementResolver($this->processors, $this->logger);
    }
}
