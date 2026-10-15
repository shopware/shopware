<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductMappingCandidateProvider;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductMappingCandidateProvider::class)]
class ProductMappingCandidateProviderTest extends TestCase
{
    public function testSupportsOnlyProductLayouts(): void
    {
        $provider = new ProductMappingCandidateProvider(new ProductContentLayoutDefinition());

        static::assertTrue($provider->supports('product'));
        static::assertFalse($provider->supports('category'));
    }

    public function testOffersTypedProductFieldsAndProviderResolvedMediaCandidates(): void
    {
        $candidates = (new ProductMappingCandidateProvider(new ProductContentLayoutDefinition()))->provide('product');
        $byPath = [];
        foreach ($candidates as $candidate) {
            $byPath[$candidate->path] = $candidate;
        }

        static::assertSame([
            'product',
            'product.name',
            'product.description',
            'product.productNumber',
            'product.metaTitle',
            'product.metaDescription',
            'product.keywords',
            'product.cover',
            'product.media',
        ], array_keys($byPath));
        static::assertSame(SalesChannelProductEntity::class, $byPath['product']->valueType);
        static::assertTrue($byPath['product']->source->isSameAs(MappingSourceReference::root('product')));
        static::assertSame('string', $byPath['product.name']->valueType);
        static::assertSame('seo', $byPath['product.metaTitle']->group);
        static::assertSame(MediaEntity::class, $byPath['product.cover']->valueType);
        static::assertSame(MediaCollection::class, $byPath['product.media']->valueType);
        static::assertSame(ContextType::Collection, $byPath['product.media']->contextType);
    }

    public function testResolvesTheActiveTranslatedProductValueThroughItsAccessor(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setName('Raw variant name');
        $product->setTranslated(['name' => 'Inherited storefront name']);
        $provider = new ProductMappingCandidateProvider(new ProductContentLayoutDefinition());
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), ['product' => $product], [], null, null, null);

        static::assertSame('Inherited storefront name', $provider->resolveRootSource(MappingSourceReference::root('product', 'name'), $context)->value);
        static::assertSame($product, $provider->resolveRootSource(MappingSourceReference::root('product'), $context)->value);
    }

    public function testResolvesMediaWithoutAProjectionAndPreservesTheProductCollection(): void
    {
        $first = $this->assignment(0);
        $second = $this->assignment(1);
        $unloaded = new ProductMediaEntity();
        $unloaded->setUniqueIdentifier(Uuid::randomHex());
        $unloaded->setPosition(2);

        $assignments = new ProductMediaCollection([$second, $unloaded, $first]);
        $product = new SalesChannelProductEntity();
        $product->setCover($first);
        $product->setMedia($assignments);

        $provider = new ProductMappingCandidateProvider(new ProductContentLayoutDefinition());
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), ['product' => $product], [], null, null, null);

        static::assertSame($first->getMedia(), $provider->resolveRootSource(MappingSourceReference::root('product', 'cover'), $context)->value);
        $media = $provider->resolveRootSource(MappingSourceReference::root('product', 'media'), $context)->value;
        static::assertInstanceOf(MediaCollection::class, $media);
        static::assertSame([$first->getMedia(), $second->getMedia()], array_values($media->getElements()));
        static::assertSame([$second, $unloaded, $first], array_values($assignments->getElements()));

        $product->setMedia(new ProductMediaCollection());
        static::assertCount(0, $provider->resolveRootSource(MappingSourceReference::root('product', 'media'), $context)->value);
    }

    private function assignment(int $position): ProductMediaEntity
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier(Uuid::randomHex());

        $assignment = new ProductMediaEntity();
        $assignment->setUniqueIdentifier(Uuid::randomHex());
        $assignment->setPosition($position);
        $assignment->setMedia($media);

        return $assignment;
    }
}
