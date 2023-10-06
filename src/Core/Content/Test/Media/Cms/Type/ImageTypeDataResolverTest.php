<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Cms\Type;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageStruct;
use Shopware\Core\Content\Media\Cms\DefaultMediaResolver;
use Shopware\Core\Content\Media\Cms\ImageCmsElementResolver;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ImageTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURES_DIRECTORY = '/../../fixtures/';

    private ImageCmsElementResolver $imageResolver;

    private FilesystemOperator $publicFilesystem;

    protected function setUp(): void
    {
        $this->publicFilesystem = $this->getPublicFilesystem();
        $this->imageResolver = new ImageCmsElementResolver(new DefaultMediaResolver($this->publicFilesystem));
    }

    public function testType(): void
    {
        static::assertSame('image', $this->imageResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->imageResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithMediaId(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_STATIC, 'media123'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->imageResolver->collect($slot, $resolverContext);

        static::assertCount(1, $criteriaCollection);

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
        $slot->setType('image');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        /** @var ImageStruct|null $imageStruct */
        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertEmpty($imageStruct->getUrl());
        static::assertEmpty($imageStruct->getMedia());
        static::assertEmpty($imageStruct->getMediaId());
    }

    public function testEnrichWithUrlOnly(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('url', FieldConfig::SOURCE_STATIC, 'http://shopware.com/image.jpg'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['url' => 'http://shopware.com/image.jpg']);
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        /** @var ImageStruct|null $imageStruct */
        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('http://shopware.com/image.jpg', $imageStruct->getUrl());
        static::assertEmpty($imageStruct->getMedia());
        static::assertEmpty($imageStruct->getMediaId());
    }

    public function testEnrichWithUrlAndNewTabOnly(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('url', FieldConfig::SOURCE_STATIC, 'http://shopware.com/image.jpg'));
        $fieldConfig->add(new FieldConfig('newTab', FieldConfig::SOURCE_STATIC, true));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['url' => 'http://shopware.com/image.jpg']);
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        /** @var ImageStruct|null $imageStruct */
        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('http://shopware.com/image.jpg', $imageStruct->getUrl());
        static::assertTrue($imageStruct->getNewTab());
        static::assertEmpty($imageStruct->getMedia());
        static::assertEmpty($imageStruct->getMediaId());
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
        $slot->setType('image');
        $slot->setConfig(['media' => 'media123', 'source' => FieldConfig::SOURCE_STATIC]);
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        /** @var ImageStruct|null $imageStruct */
        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertEmpty($imageStruct->getUrl());
        static::assertInstanceOf(MediaEntity::class, $imageStruct->getMedia());
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }

    public function testEnrichWithMediaAndUrl(): void
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
        $fieldConfig->add(new FieldConfig('url', FieldConfig::SOURCE_STATIC, 'http://shopware.com/image.jpg'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['mediaId' => 'media123', 'url' => 'http://shopware.com/image.jpg']);
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        /** @var ImageStruct|null $imageStruct */
        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame('http://shopware.com/image.jpg', $imageStruct->getUrl());
        static::assertInstanceOf(MediaEntity::class, $imageStruct->getMedia());
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
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
        $slot->setType('image');
        $slot->setConfig(['mediaId' => 'media123']);
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        /** @var ImageStruct|null $imageStruct */
        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertEmpty($imageStruct->getUrl());
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertEmpty($imageStruct->getMedia());
    }

    public function testEnrichWithDefaultConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $this->publicFilesystem->write('/bundles/core/assets/default/cms/shopware.jpg', '');

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('media', FieldConfig::SOURCE_DEFAULT, 'core/assets/default/cms/shopware.jpg'));

        $slot = new CmsSlotEntity();
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        /** @var ImageStruct|null $imageStruct */
        $imageStruct = $slot->getData();
        $media = $imageStruct->getMedia();

        static::assertEquals('shopware', $media->getFileName());
        static::assertEquals('image/jpeg', $media->getMimeType());
        static::assertEquals('jpg', $media->getFileExtension());
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
        $slot->setType('image');
        $slot->setConfig(json_decode(json_encode($fieldConfig, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR));
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        /** @var ImageStruct|null $imageStruct */
        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertEmpty($imageStruct->getUrl());
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
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
        $slot->setType('image');
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        /** @var ImageStruct|null $imageStruct */
        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertEmpty($imageStruct->getUrl());
        static::assertSame('media123', $imageStruct->getMediaId());
        static::assertSame($media, $imageStruct->getMedia());
    }

    public function testUrlWithLocal(): void
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setLink('http://shopware.com');

        $product = new ProductEntity();
        $product->setManufacturer($manufacturer);

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(ProductDefinition::class), $product);

        $result = new ElementDataCollection();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('url', FieldConfig::SOURCE_MAPPED, 'manufacturer.link'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setFieldConfig($fieldConfig);

        $this->imageResolver->enrich($slot, $resolverContext, $result);

        /** @var ImageStruct|null $imageStruct */
        $imageStruct = $slot->getData();
        static::assertInstanceOf(ImageStruct::class, $imageStruct);
        static::assertSame($manufacturer->getLink(), $imageStruct->getUrl());
        static::assertEmpty($imageStruct->getMediaId());
        static::assertEmpty($imageStruct->getMedia());
    }
}
