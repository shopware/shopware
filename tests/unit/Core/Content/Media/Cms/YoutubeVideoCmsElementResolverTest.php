<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageStruct;
use Shopware\Core\Content\Media\Cms\YoutubeVideoCmsElementResolver;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(YoutubeVideoCmsElementResolver::class)]
class YoutubeVideoCmsElementResolverTest extends TestCase
{
    public function testCollectReturnsNullWithMappedConfigAndResolverContextWithoutEntity(): void
    {
        $resolver = new YoutubeVideoCmsElementResolver();

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('previewMedia', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroPreview'),
        ]));

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectReturnsNullWithMappedConfigResolvedToMediaEntity(): void
    {
        $resolver = new YoutubeVideoCmsElementResolver();

        $media = new MediaEntity();
        $media->setId('media-1');

        $productMedia = new ProductMediaEntity();
        $productMedia->setMedia($media);

        $product = new ProductEntity();
        $product->setCover($productMedia);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            $this->createMock(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('previewMedia', FieldConfig::SOURCE_MAPPED, 'cover.media'),
        ]));

        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectCreatesMediaCriteriaWithMappedStringId(): void
    {
        $resolver = new YoutubeVideoCmsElementResolver();

        $product = new ProductEntity();
        $product->setCustomFields(['heroPreview' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            $this->createMock(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('previewMedia', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroPreview'),
        ]));

        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $definitionData = $collection->all()[MediaDefinition::class] ?? null;
        static::assertIsArray($definitionData);
        static::assertArrayHasKey('media_slot-1', $definitionData);

        $criteria = $definitionData['media_slot-1'];
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame(['media-1'], $criteria->getIds());
    }

    public function testEnrichMappedStringMediaSetsMediaIdAndMedia(): void
    {
        $resolver = new YoutubeVideoCmsElementResolver();

        $product = new ProductEntity();
        $product->setCustomFields(['heroPreview' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            $this->createMock(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('previewMedia', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroPreview'),
        ]));

        $media = new MediaEntity();
        $media->setId('media-1');

        $searchResult = $this->createMock(EntitySearchResult::class);
        $searchResult->method('get')->with('media-1')->willReturn($media);

        $data = new ElementDataCollection();
        $data->add('media_slot-1', $searchResult);

        $resolver->enrich($slot, $context, $data);

        $imageData = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageData);
        static::assertSame('media-1', $imageData->getMediaId());
        static::assertSame($media, $imageData->getMedia());
    }

    public function testEnrichMappedStringMediaSetsOnlyMediaIdWhenSearchResultMissing(): void
    {
        $resolver = new YoutubeVideoCmsElementResolver();

        $product = new ProductEntity();
        $product->setCustomFields(['heroPreview' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            $this->createMock(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('previewMedia', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroPreview'),
        ]));

        $resolver->enrich($slot, $context, new ElementDataCollection());

        $imageData = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageData);
        static::assertSame('media-1', $imageData->getMediaId());
        static::assertNull($imageData->getMedia());
    }
}
