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

    private LoggerInterface&MockObject $logger;

    /**
     * @var AbstractProductSliderProcessor[]
     */
    private array $processors = [];

    protected function setUp(): void
    {
        $this->config = new FieldConfigCollection();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testGetType(): void
    {
        $this->logger->expects($this->never())->method('error');

        static::assertSame('product-slider', $this->getResolver()->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $this->logger->expects($this->never())->method('error');

        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, null));

        $slot = $this->getSlot();
        $collection = $this->getResolver()->collect($slot, $this->getResolverContext());

        static::assertNull($collection);
    }

    public function testCollectNoProcessorFound(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $this->logger->expects($this->once())->method('error')
            ->with('No product slider processor found by provided source: "static"');

        $processor = $this->createMock(AbstractProductSliderProcessor::class);
        $processor->expects($this->once())->method('getSource')->willReturn('not-existing-processor');
        $this->processors[] = $processor;

        $slot = $this->getSlot();
        $collection = $this->getResolver()->collect($slot, $this->getResolverContext());
        static::assertNull($collection);
    }

    public function testCollect(): void
    {
        $this->logger->expects($this->never())->method('error');

        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $collection = new CriteriaCollection();
        $collection->add('product', ProductDefinition::class, new Criteria());

        $processor = $this->createMock(AbstractProductSliderProcessor::class);
        $processor->method('getSource')->willReturn(FieldConfig::SOURCE_STATIC);
        $processor->expects($this->once())
            ->method('collect')
            ->willReturn($collection);

        $this->processors['static'] = $processor;

        $slot = $this->getSlot();
        static::assertSame($collection, $this->getResolver()->collect($slot, $this->getResolverContext()));
    }

    public function testEnrichWithEmptyConfig(): void
    {
        // No processor is registered, so the resolver logs the missing "static" processor.
        $this->logger->expects($this->once())->method('error');

        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, null));

        $slot = $this->getSlot();
        $data = new ElementDataCollection();

        $processor = $this->createMock(AbstractProductSliderProcessor::class);
        $processor->expects($this->never())->method('enrich');

        $this->getResolver()->enrich($slot, $this->getResolverContext(), $data);
    }

    public function testEnrichNoProcessorFound(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $this->logger->expects($this->once())->method('error')
            ->with('No product slider processor found by provided source: "static"');

        $processor = $this->createMock(AbstractProductSliderProcessor::class);
        $processor->expects($this->once())->method('getSource')->willReturn('not-existing-processor');
        $processor->expects($this->never())->method('enrich');
        $this->processors[] = $processor;

        $slot = $this->getSlot();
        $data = new ElementDataCollection();

        $this->getResolver()->enrich($slot, $this->getResolverContext(), $data);
    }

    public function testEnrich(): void
    {
        $this->logger->expects($this->never())->method('error');

        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $slot = $this->getSlot();
        $data = new ElementDataCollection();
        $resolverContext = $this->getResolverContext();

        $processor = $this->createMock(AbstractProductSliderProcessor::class);
        $processor->method('getSource')->willReturn(FieldConfig::SOURCE_STATIC);
        $processor->expects($this->once())->method('enrich')->with($slot, $data, $resolverContext);

        $this->processors['static'] = $processor;

        $this->getResolver()->enrich($slot, $this->getResolverContext(), $data);
    }

    private function getResolver(): ProductSliderCmsElementResolver
    {
        return new ProductSliderCmsElementResolver($this->processors, $this->logger);
    }
}
