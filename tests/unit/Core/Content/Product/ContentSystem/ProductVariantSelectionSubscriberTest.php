<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\ProductVariantSelectionSubscriber;
use Shopware\Core\Content\Product\SalesChannel\Detail\PartialProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
use Shopware\Core\Framework\ContentSystem\Event\RenderedTreeFinalizationEvent;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\ContentSystem\Rendering\RenderedElement;
use Shopware\Core\Framework\ContentSystem\RenderingSpecification;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductVariantSelectionSubscriber::class)]
class ProductVariantSelectionSubscriberTest extends TestCase
{
    private ProductConfiguratorLoader&MockObject $loader;

    private ProductVariantSelectionSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->loader = static::createMock(ProductConfiguratorLoader::class);
        $this->subscriber = new ProductVariantSelectionSubscriber(new PartialProductConfiguratorLoader($this->loader));
    }

    public function testOptionsFollowTheEffectiveProductInsteadOfThePageProduct(): void
    {
        $product = $this->product('chosen-variant', 'chosen-parent');
        $product->setOptionIds(['chosen-option']);
        $stale = new PropertyGroupCollection();
        $group = new PropertyGroupEntity();
        $group->setId('chosen-group');
        $groups = new PropertyGroupCollection([$group]);
        $element = new RenderedElement('selector', 'Sw:Product:VariantSelection', ['product' => $product, 'configuratorSettings' => $stale]);
        $event = $this->event([new RenderedElement('container', 'Sw:Grid:Container', slots: ['content' => [$element]])]);
        $this->loader->expects($this->once())->method('load')
            ->with(static::callback(static function (SalesChannelProductEntity $actual): bool {
                static::assertSame('chosen-variant', $actual->getId());
                static::assertSame('chosen-parent', $actual->getParentId());
                static::assertSame(['chosen-option'], $actual->getOptionIds());

                return true;
            }), $event->salesChannelContext)
            ->willReturn($groups);

        $this->subscriber->onRenderedTreeFinalization($event);

        $result = $event->tree()[0]->slots['content'][0];
        static::assertSame($product, $result->properties['product']);
        static::assertSame($groups, $result->properties['configuratorSettings']);
        static::assertSame($stale, $element->properties['configuratorSettings']);
        static::assertSame([
            EntityCacheKeyGenerator::buildProductTag('chosen-variant'),
            EntityCacheKeyGenerator::buildProductTag('chosen-parent'),
        ], $event->cacheContext->getTags());
    }

    public function testAParentProductLoadsItsOwnConfiguratorSettings(): void
    {
        $product = $this->product('parent');
        $event = $this->event([new RenderedElement('selector', 'Sw:Product:VariantSelection', ['product' => $product])]);
        $this->loader->expects($this->once())->method('load')
            ->with(static::callback(static function (SalesChannelProductEntity $actual): bool {
                static::assertSame('parent', $actual->getId());
                static::assertSame('parent', $actual->getParentId());

                return true;
            }), $event->salesChannelContext)
            ->willReturn(new PropertyGroupCollection());

        $this->subscriber->onRenderedTreeFinalization($event);

        static::assertSame([EntityCacheKeyGenerator::buildProductTag('parent')], $event->cacheContext->getTags());
        static::assertInstanceOf(PropertyGroupCollection::class, $event->tree()[0]->properties['configuratorSettings']);
        static::assertCount(0, $event->tree()[0]->properties['configuratorSettings']);
    }

    public function testOptionsAreSharedOnlyWithinOneRenderAndForTheSameVariant(): void
    {
        $first = $this->product('first-variant', 'parent');
        $second = $this->product('second-variant', 'parent');
        $firstGroups = new PropertyGroupCollection();
        $secondGroups = new PropertyGroupCollection();
        $this->loader->expects($this->exactly(4))->method('load')
            ->willReturnCallback(static fn (SalesChannelProductEntity $product): PropertyGroupCollection => $product->getId() === 'first-variant' ? $firstGroups : $secondGroups);

        for ($i = 0; $i < 2; ++$i) {
            $event = $this->event([
                new RenderedElement('first', 'Sw:Product:VariantSelection', ['product' => $first]),
                new RenderedElement('duplicate', 'Sw:Product:VariantSelection', ['product' => $first]),
                new RenderedElement('second', 'Sw:Product:VariantSelection', ['product' => $second]),
            ]);

            $this->subscriber->onRenderedTreeFinalization($event);

            static::assertSame($firstGroups, $event->tree()[0]->properties['configuratorSettings']);
            static::assertSame($firstGroups, $event->tree()[1]->properties['configuratorSettings']);
            static::assertSame($secondGroups, $event->tree()[2]->properties['configuratorSettings']);
        }
    }

    /**
     * @param array<string, mixed> $properties
     */
    #[DataProvider('unavailableProductCases')]
    public function testUnavailableProductsCannotRetainUnrelatedOptions(array $properties): void
    {
        $properties['configuratorSettings'] = new PropertyGroupCollection();
        $event = $this->event([new RenderedElement('selector', 'Sw:Product:VariantSelection', $properties)]);
        $this->loader->expects($this->never())->method('load');

        $this->subscriber->onRenderedTreeFinalization($event);

        static::assertNull($event->tree()[0]->properties['configuratorSettings']);
        static::assertSame([], $event->cacheContext->getTags());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unavailableProductCases(): iterable
    {
        yield 'no product was delivered' => [[]];
        yield 'mapping delivered explicit null' => [['product' => null]];
    }

    public function testSkeletonsAndOtherComponentsDoNotLoadOptions(): void
    {
        $skeleton = new RenderedElement('skeleton', 'Sw:Product:VariantSelection');
        $other = new RenderedElement('other', 'Sw:Product:Manufacturer', ['product' => $this->product('product')]);
        $event = $this->event([$skeleton, $other]);
        $this->loader->expects($this->never())->method('load');

        $this->subscriber->onRenderedTreeFinalization($event);

        static::assertSame([$skeleton, $other], $event->tree());
        static::assertSame([], $event->cacheContext->getTags());
    }

    public function testConfiguratorErrorsPropagate(): void
    {
        $event = $this->event([new RenderedElement('selector', 'Sw:Product:VariantSelection', ['product' => $this->product('product')])]);
        $error = new \RuntimeException('Configurator failed');
        $this->loader->expects($this->once())->method('load')->willThrowException($error);
        $this->expectExceptionObject($error);

        $this->subscriber->onRenderedTreeFinalization($event);
    }

    private function product(string $id, ?string $parentId = null): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($id);
        $product->setParentId($parentId);

        return $product;
    }

    /**
     * @param list<RenderedElement> $tree
     */
    private function event(array $tree): RenderedTreeFinalizationEvent
    {
        return new RenderedTreeFinalizationEvent(
            $tree,
            LayoutReference::create('layout', 'Layout', '1'),
            new RenderingSpecification([], PlaceholderValues::from(['productId' => 'page-product']), new Request()),
            Generator::generateSalesChannelContext(),
            new RenderingCacheContext(),
        );
    }
}
