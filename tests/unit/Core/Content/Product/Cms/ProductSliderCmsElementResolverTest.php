<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver;
use Shopware\Core\Content\Product\Cms\Utils\ProductSlider\AbstractProductSliderHandler;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Content\Product\Cms\Utils\ProductSlider\ProductSliderUnitTrait;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductSliderCmsElementResolver::class)]
class ProductSliderCmsElementResolverTest extends TestCase
{
    use ProductSliderUnitTrait;

    private FieldConfigCollection $config;

    private AbstractProductSliderHandler&MockObject $handler;

    /**
     * @var AbstractProductSliderHandler[]
     */
    private array $handlers = [];

    protected function setUp(): void
    {
        $this->config = new FieldConfigCollection();
        $this->handler = $this->createMock(AbstractProductSliderHandler::class);
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

    public function testCollectNoHandlerFound(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $this->handler->expects(static::once())->method('getSource')->willReturn('not-existing-handler');
        $this->handlers[] = $this->handler;

        $slot = $this->getSlot();
        $collection = $this->getResolver()->collect($slot, $this->getResolverContext());
        static::assertNull($collection);
    }

    public function testCollect(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $collection = new CriteriaCollection();
        $collection->add('product', ProductDefinition::class, new Criteria());

        $this->handler->method('getSource')->willReturn(FieldConfig::SOURCE_STATIC);
        $this->handler->expects(static::once())
            ->method('collect')
            ->willReturn($collection);

        $this->handlers['static'] = $this->handler;

        $slot = $this->getSlot();
        static::assertSame($collection, $this->getResolver()->collect($slot, $this->getResolverContext()));
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, null));

        $slot = $this->getSlot();
        $data = new ElementDataCollection();

        $handler = $this->createMock(AbstractProductSliderHandler::class);
        $handler->expects(static::never())->method('enrich');

        $this->getResolver()->enrich($slot, $this->getResolverContext(), $data);
    }

    public function testEnrichNoHandlerFound(): void
    {
        $this->config->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'VALID-VALUE'));

        $this->handler->expects(static::once())->method('getSource')->willReturn('not-existing-handler');
        $this->handler->expects(static::never())->method('enrich');
        $this->handlers[] = $this->handler;

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

        $handler = $this->createMock(AbstractProductSliderHandler::class);
        $handler->method('getSource')->willReturn(FieldConfig::SOURCE_STATIC);
        $handler->expects(static::once())->method('enrich')->with($slot, $data, $resolverContext);

        $this->handlers['static'] = $handler;

        $this->getResolver()->enrich($slot, $this->getResolverContext(), $data);
    }

    private function getResolver(): ProductSliderCmsElementResolver
    {
        return new ProductSliderCmsElementResolver($this->handlers);
    }
}
