<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductContentLayout;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductMappingCandidateProvider;
use Shopware\Core\Content\Product\ContentSystem\Mapping\ProductMediaCollectionToMediaCollectionProjection;
use Shopware\Core\Content\Product\ContentSystem\Mapping\ProductMediaToMediaProjection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\Log\Package;

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

    public function testOffersTypedProductFieldsAndProjectedMediaCandidates(): void
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
        static::assertSame(ProductMediaToMediaProjection::NAME, $byPath['product.cover']->projection);
        static::assertSame(MediaCollection::class, $byPath['product.media']->valueType);
        static::assertSame(ContextType::Collection, $byPath['product.media']->contextType);
        static::assertSame(ProductMediaCollectionToMediaCollectionProjection::NAME, $byPath['product.media']->projection);
    }
}
