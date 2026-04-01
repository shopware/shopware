<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Cms\Type;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\VideoStruct;
use Shopware\Core\Content\Media\Cms\DefaultMediaResolver;
use Shopware\Core\Content\Media\Cms\VideoCmsElementResolver;
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
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class VideoTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private VideoCmsElementResolver $videoResolver;

    private FilesystemOperator $publicFilesystem;

    protected function setUp(): void
    {
        $this->publicFilesystem = $this->getPublicFilesystem();
        $this->videoResolver = new VideoCmsElementResolver(new DefaultMediaResolver($this->publicFilesystem));
    }

    public function testType(): void
    {
        static::assertSame('video', $this->videoResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->videoResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithMediaId(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->videoResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);
        static::assertCount(1, iterator_to_array($criteriaCollection));

        $expectedCriteria = new Criteria(['media123']);

        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media_' . $slot->getUniqueIdentifier()];

        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testCollectWithMappedMediaCustomFieldId(): void
    {
        $product = new ProductEntity();
        $product->setCustomFields(['heroVideo' => 'media123']);

        $resolverContext = new EntityResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
            $this->createMock(ProductDefinition::class),
            $product,
        );

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroVideo'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->videoResolver->collect($slot, $resolverContext);

        static::assertNotNull($criteriaCollection);

        $expectedCriteria = new Criteria(['media123']);
        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media_' . $slot->getUniqueIdentifier()];

        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertEmpty($videoStruct->getMedia());
        static::assertEmpty($videoStruct->getMediaId());
    }

    public function testEnrichWithMediaOnly(): void
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
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setConfig(['media' => 'media123', 'source' => FieldConfig::SOURCE_STATIC]);
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertInstanceOf(MediaEntity::class, $videoStruct->getMedia());
        static::assertSame('media123', $videoStruct->getMediaId());
        static::assertSame($media, $videoStruct->getMedia());
    }

    public function testEnrichWithMissingMediaId(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $mediaSearchResult = new EntitySearchResult(
            'media',
            0,
            new MediaCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new ElementDataCollection();
        $result->add('media', $mediaSearchResult);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setConfig(['mediaId' => 'media123']);
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertSame('media123', $videoStruct->getMediaId());
        static::assertEmpty($videoStruct->getMedia());
    }

    public function testEnrichWithDefaultConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $this->publicFilesystem->write('/bundles/storefront/assets/default/cms/shopware.mp4', '');

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_DEFAULT, 'bundles/storefront/assets/default/cms/shopware.mp4'));

        $slot = new CmsSlotEntity();
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        $media = $videoStruct->getMedia();
        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertSame('shopware', $media->getFileName());
        static::assertSame('video/mp4', $media->getMimeType());
        static::assertSame('mp4', $media->getFileExtension());
    }

    public function testMediaWithRemote(): void
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
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setConfig(json_decode(json_encode($fieldConfig, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertSame('media123', $videoStruct->getMediaId());
        static::assertSame($media, $videoStruct->getMedia());
    }

    public function testMediaWithLocal(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $productMedia = new ProductMediaEntity();
        $productMedia->setMedia($media);

        $product = new ProductEntity();
        $product->setCover($productMedia);

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);

        $mediaSearchResult = new EntitySearchResult(
            'media',
            0,
            new MediaCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new ElementDataCollection();
        $result->add('media_id', $mediaSearchResult);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'cover.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertSame('media123', $videoStruct->getMediaId());
        static::assertSame($media, $videoStruct->getMedia());
    }

    public function testMediaWithMappedCustomFieldId(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $product = new ProductEntity();
        $product->setCustomFields(['heroVideo' => 'media123']);

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
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_MAPPED, 'product.customFields.heroVideo'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('video');
        $slot->setFieldConfig($fieldConfig);

        $this->videoResolver->enrich($slot, $resolverContext, $result);

        $videoStruct = $slot->getData();
        static::assertInstanceOf(VideoStruct::class, $videoStruct);
        static::assertSame('media123', $videoStruct->getMediaId());
        static::assertSame($media, $videoStruct->getMedia());
    }
}
