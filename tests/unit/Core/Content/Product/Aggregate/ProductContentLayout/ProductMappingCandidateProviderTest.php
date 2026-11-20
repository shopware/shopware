<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductMappingCandidateProvider;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Cache\RenderingCacheContext;
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
    #[DataProvider('textFieldProvider')]
    public function testResolvesEachTextFieldAndItsTranslationFallback(string $member, bool $translated, string $expected): void
    {
        $entity = new SalesChannelProductEntity();
        $entity->assign([
            'name' => 'Raw name',
            'description' => 'Raw description',
            'productNumber' => 'Raw productNumber',
            'metaTitle' => 'Raw metaTitle',
            'metaDescription' => 'Raw metaDescription',
            'keywords' => 'Raw keywords',
        ]);
        $entity->setTranslated($translated ? [
            'name' => 'Translated name',
            'description' => 'Translated description',
            'productNumber' => 'Translated productNumber',
            'metaTitle' => 'Translated metaTitle',
            'metaDescription' => 'Translated metaDescription',
            'keywords' => 'Translated keywords',
        ] : []);
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), ['product' => $entity], [], new RenderingCacheContext());

        static::assertSame($expected, (new ProductMappingCandidateProvider(new ProductContentLayoutDefinition()))->resolveRootSource(MappingSourceReference::root('product', $member), $context)->value);
    }

    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function textFieldProvider(): iterable
    {
        yield 'name with translations' => ['name', true, 'Translated name'];
        yield 'name raw fallback' => ['name', false, 'Raw name'];
        yield 'description with translations' => ['description', true, 'Translated description'];
        yield 'description raw fallback' => ['description', false, 'Raw description'];
        yield 'productNumber with translations' => ['productNumber', true, 'Raw productNumber'];
        yield 'productNumber raw fallback' => ['productNumber', false, 'Raw productNumber'];
        yield 'metaTitle with translations' => ['metaTitle', true, 'Translated metaTitle'];
        yield 'metaTitle raw fallback' => ['metaTitle', false, 'Raw metaTitle'];
        yield 'metaDescription with translations' => ['metaDescription', true, 'Translated metaDescription'];
        yield 'metaDescription raw fallback' => ['metaDescription', false, 'Raw metaDescription'];
        yield 'keywords with translations' => ['keywords', true, 'Translated keywords'];
        yield 'keywords raw fallback' => ['keywords', false, 'Raw keywords'];
    }

    public function testStableDeclarationsMatchTheOfferedCatalogue(): void
    {
        $provider = new ProductMappingCandidateProvider(new ProductContentLayoutDefinition());

        static::assertSame(
            ProductMappingCandidateProvider::registeredCandidateKeys()['product'],
            array_column($provider->provide('product'), 'path'),
        );
    }

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
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), ['product' => $product], [], new RenderingCacheContext());

        static::assertSame('Inherited storefront name', $provider->resolveRootSource(MappingSourceReference::root('product', 'name'), $context)->value);
        static::assertSame($product, $provider->resolveRootSource(MappingSourceReference::root('product'), $context)->value);
    }

    public function testCoverUnwrapsTheMediaAssignment(): void
    {
        $cover = $this->assignment(0);
        $product = new SalesChannelProductEntity();
        $product->setCover($cover);
        static::assertSame($cover->getMedia(), $this->resolveMedia($product, 'cover'));
    }

    public function testMediaIsSortedByPositionAndSkipsUnloadedAssignments(): void
    {
        $first = $this->assignment(0);
        $second = $this->assignment(1);
        $unloaded = new ProductMediaEntity();
        $unloaded->setUniqueIdentifier(Uuid::randomHex());
        $unloaded->setPosition(2);
        $product = new SalesChannelProductEntity();
        $product->setMedia(new ProductMediaCollection([$second, $unloaded, $first]));

        $media = $this->resolveMedia($product, 'media');

        static::assertInstanceOf(MediaCollection::class, $media);
        static::assertSame([$first->getMedia(), $second->getMedia()], array_values($media->getElements()));
    }

    public function testResolvingMediaDoesNotReorderTheAuthoredAssignmentCollection(): void
    {
        $assignments = new ProductMediaCollection([$this->assignment(1), $this->assignment(0)]);
        $before = $assignments->getElements();
        $product = new SalesChannelProductEntity();
        $product->setMedia($assignments);

        $this->resolveMedia($product, 'media');

        static::assertSame($before, $assignments->getElements());
    }

    public function testAnEmptyAssignmentCollectionResolvesToAnEmptyMediaCollection(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setMedia(new ProductMediaCollection());

        $media = $this->resolveMedia($product, 'media');

        static::assertInstanceOf(MediaCollection::class, $media);
        static::assertCount(0, $media);
    }

    private function resolveMedia(SalesChannelProductEntity $product, string $member): mixed
    {
        $provider = new ProductMappingCandidateProvider(new ProductContentLayoutDefinition());
        $context = new MappingSourceResolutionContext(new StoredElement('element-1', 'Sw:Text'), ['product' => $product], [], new RenderingCacheContext());

        return $provider->resolveRootSource(MappingSourceReference::root('product', $member), $context)->value;
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
