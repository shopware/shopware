<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Cms\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageStruct;
use Shopware\Core\Content\Media\Cms\YoutubeVideoCmsElementResolver;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(YoutubeVideoCmsElementResolver::class)]
class YoutubeVideoTypeDataResolverTest extends TestCase
{
    private YoutubeVideoCmsElementResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new YoutubeVideoCmsElementResolver();
    }

    public function testType(): void
    {
        static::assertSame('youtube-video', $this->resolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->resolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithPreviewMediaId(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('previewMedia', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->resolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, iterator_to_array($criteriaCollection));

        $expectedCriteria = new Criteria(['media123']);
        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media_' . $slot->getUniqueIdentifier()];

        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testCollectWithMappedPreviewMediaCustomFieldId(): void
    {
        $product = new ProductEntity();
        $product->setCustomFields(['heroPreview' => 'media123']);

        $resolverContext = new EntityResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
            $this->createMock(ProductDefinition::class),
            $product,
        );

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('previewMedia', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroPreview'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->resolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);

        $expectedCriteria = new Criteria(['media123']);
        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media_' . $slot->getUniqueIdentifier()];

        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testEnrichWithMappedPreviewMediaCustomFieldId(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $product = new ProductEntity();
        $product->setCustomFields(['heroPreview' => 'media123']);

        $resolverContext = new EntityResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
            $this->createMock(ProductDefinition::class),
            $product,
        );

        $mediaSearchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new ElementDataCollection();
        $result->add('media_id', $mediaSearchResult);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('previewMedia', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroPreview'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->resolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertEmpty($imageStruct->getMediaId());
        static::assertEmpty($imageStruct->getMedia());
    }

    public function testEnrichWithPreviewMediaOnly(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $mediaSearchResult = new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new ElementDataCollection();
        $result->add('media_id', $mediaSearchResult);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('previewMedia', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }

    public function testEnrichWithMappedPreviewMediaEntity(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $productMedia = new ProductMediaEntity();
        $productMedia->setMedia($media);

        $product = new ProductEntity();
        $product->setCover($productMedia);

        $resolverContext = new EntityResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
            $this->createMock(ProductDefinition::class),
            $product,
        );

        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('previewMedia', FieldConfig::SOURCE_MAPPED, 'cover.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('youtube-video');
        $slot->setFieldConfig($fieldConfig);

        $this->resolver->enrich($slot, $resolverContext, $result);

        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }
}
