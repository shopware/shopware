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
use Shopware\Core\Content\Cms\SalesChannel\Struct\VideoStruct;
use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\Cms\VideoCmsElementResolver;
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
#[CoversClass(VideoCmsElementResolver::class)]
class VideoCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $resolver = new VideoCmsElementResolver($this->createMock(AbstractDefaultMediaResolver::class));
        static::assertSame('video', $resolver->getType());
    }

    public function testCollectCreatesMediaCriteria(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $resolver = new VideoCmsElementResolver($this->createMock(AbstractDefaultMediaResolver::class));
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $elements = $collection->all();
        static::assertCount(1, $elements);
        static::assertArrayHasKey(MediaDefinition::class, $elements);

        $definitionData = array_shift($elements);
        static::assertCount(1, $definitionData);
        static::assertArrayHasKey('media_slot-1', $definitionData);

        $criteria = array_shift($definitionData);
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame(['media-1'], $criteria->getIds());
    }

    public function testCollectReturnsNullWithMappedConfig(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'media'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $resolver = new VideoCmsElementResolver($this->createMock(AbstractDefaultMediaResolver::class));
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectReturnsNullWithMappedConfigResolvedToMediaEntity(): void
    {
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
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'cover.media'),
        ]));

        $resolver = new VideoCmsElementResolver($this->createMock(AbstractDefaultMediaResolver::class));
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testCollectCreatesMediaCriteriaWithMappedStringId(): void
    {
        $product = new ProductEntity();
        $product->setCustomFields(['heroVideo' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            $this->createMock(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroVideo'),
        ]));

        $resolver = new VideoCmsElementResolver($this->createMock(AbstractDefaultMediaResolver::class));
        $collection = $resolver->collect($slot, $context);

        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $definitionData = $collection->all()[MediaDefinition::class] ?? null;
        static::assertIsArray($definitionData);
        static::assertArrayHasKey('media_slot-1', $definitionData);

        $criteria = $definitionData['media_slot-1'];
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertSame(['media-1'], $criteria->getIds());
    }

    public function testEnrichStaticSlotWithMedia(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $media = new MediaEntity();
        $media->setId('media-1');

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('get')->with('media-1')->willReturn($media);

        $data = new ElementDataCollection();
        $data->add('media_slot-1', $result);

        $resolver = new VideoCmsElementResolver($this->createMock(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $data);
        static::assertSame('media-1', $data->getMediaId());

        $media = $data->getMedia();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('media-1', $media->getId());
    }

    public function testEnrichWithDefaultMedia(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_DEFAULT, 'bundles/storefront/assets/default/cms/shopware.mp4'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $defaultMedia = new MediaEntity();
        $defaultMedia->setId('default-1');

        $mediaResolver = $this->createMock(AbstractDefaultMediaResolver::class);
        $mediaResolver->method('getDefaultCmsMediaEntity')->willReturn($defaultMedia);

        $resolver = new VideoCmsElementResolver($mediaResolver);
        $resolver->enrich($slot, $context, new ElementDataCollection());

        $data = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $data);
        static::assertSame($defaultMedia, $data->getMedia());
        static::assertNull($data->getMediaId());
    }

    public function testEnrichWithAriaLabel(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media-1'),
            new FieldConfig('ariaLabel', FieldConfig::SOURCE_STATIC, 'Video description for accessibility'),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);

        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $media = new MediaEntity();
        $media->setId('media-1');

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('get')->with('media-1')->willReturn($media);

        $data = new ElementDataCollection();
        $data->add('media_slot-1', $result);

        $resolver = new VideoCmsElementResolver($this->createMock(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, $data);

        $videoData = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoData);
        static::assertSame('Video description for accessibility', $videoData->getAriaLabel());
    }

    public function testEnrichMappedStringMediaSetsMediaIdAndMedia(): void
    {
        $product = new ProductEntity();
        $product->setCustomFields(['heroVideo' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            $this->createMock(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroVideo'),
        ]));

        $media = new MediaEntity();
        $media->setId('media-1');

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('get')->with('media-1')->willReturn($media);

        $data = new ElementDataCollection();
        $data->add('media_slot-1', $result);

        $resolver = new VideoCmsElementResolver($this->createMock(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, $data);

        $videoData = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoData);
        static::assertSame('media-1', $videoData->getMediaId());
        static::assertSame($media, $videoData->getMedia());
    }

    public function testEnrichMappedStringMediaSetsOnlyMediaIdWhenSearchResultMissing(): void
    {
        $product = new ProductEntity();
        $product->setCustomFields(['heroVideo' => 'media-1']);

        $context = new EntityResolverContext(
            Generator::generateSalesChannelContext(),
            new Request(),
            $this->createMock(ProductDefinition::class),
            $product,
        );

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig(new FieldConfigCollection([
            new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroVideo'),
        ]));

        $resolver = new VideoCmsElementResolver($this->createMock(AbstractDefaultMediaResolver::class));
        $resolver->enrich($slot, $context, new ElementDataCollection());

        $videoData = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoData);
        static::assertSame('media-1', $videoData->getMediaId());
        static::assertNull($videoData->getMedia());
    }
}
