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
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Shopware\Core\Content\Media\Cms\DefaultMediaResolver;
use Shopware\Core\Content\Media\Cms\Type\ImageSliderTypeDataResolver;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class ImageSliderTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ImageSliderTypeDataResolver $imageSliderResolver;

    private FilesystemOperator $publicFilesystem;

    protected function setUp(): void
    {
        $this->publicFilesystem = $this->getPublicFilesystem();
        $this->imageSliderResolver = new ImageSliderTypeDataResolver(new DefaultMediaResolver($this->publicFilesystem));
    }

    public function testType(): void
    {
        static::assertSame('image-slider', $this->imageSliderResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $criteriaCollection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNull($criteriaCollection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $medias = [
            ['mediaId' => 'media123'],
            ['mediaId' => 'media456'],
        ];

        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_STATIC, $medias));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $criteriaCollection = $this->imageSliderResolver->collect($slot, $resolverContext);
        static::assertNotNull($criteriaCollection);
        static::assertCount(1, iterator_to_array($criteriaCollection));

        $expectedCriteria = new Criteria(['media123', 'media456']);

        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media_' . $slot->getUniqueIdentifier()];
        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testCollectWithMappedConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithMappedConfigInEntityContextCreatesProductMediaCriteria(): void
    {
        $resolverContext = $this->getResolverContextWithoutMedia();

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNotNull($collection);
        static::assertCount(1, iterator_to_array($collection));

        $criteria = $collection->all()[ProductMediaDefinition::class]['product_media_' . $slot->getUniqueIdentifier()];
        static::assertCount(1, $criteria->getFilters());
        $productMediaFilter = $criteria->getFilters()[0];
        static::assertInstanceOf(EqualsFilter::class, $productMediaFilter);
        static::assertSame('productId', $productMediaFilter->getField());
        static::assertSame('product_01', $productMediaFilter->getValue());
        static::assertArrayHasKey('media', $criteria->getAssociations());
        static::assertCount(1, $criteria->getSorting());
        static::assertSame('position', $criteria->getSorting()[0]->getField());
    }

    public function testCollectWithMappedConfigAndAlreadyResolvedMediaSkipsAdditionalCriteria(): void
    {
        $resolverContext = $this->getResolverContext($this->getProductMediaCollection());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithMappedConfigAndPartiallyResolvedMediaCreatesAdditionalCriteria(): void
    {
        $resolverContext = $this->getResolverContext($this->getProductMediaCollectionWithoutAssociatedMedia());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNotNull($collection);
        static::assertArrayHasKey(ProductMediaDefinition::class, $collection->all());
        static::assertArrayHasKey('product_media_id', $collection->all()[ProductMediaDefinition::class]);
    }

    public function testCollectWithDefaultConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_DEFAULT, 'my_default_media.png'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->imageSliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setConfig([]);
        $slot->setFieldConfig(new FieldConfigCollection());

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);
        static::assertEmpty($imageSliderStruct->getSliderItems());
    }

    public function testEnrichWithMappedConfigAndHasCorrectOrder(): void
    {
        $productMediaCollection = $this->getProductMediaCollection();
        $resolverContext = $this->getResolverContext($productMediaCollection);
        $result = $this->getEntitySearchResult($productMediaCollection, $resolverContext);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $sliderItems = $imageSliderStruct->getSliderItems();
        static::assertIsArray($sliderItems);

        $expectedSliderIds = ['media0', 'media1', 'media2', 'media3', 'media4'];
        $imageSliderIds = array_map(static fn ($value) => $value->getMedia()?->getId() ?? '', $sliderItems);

        static::assertSame($expectedSliderIds, $imageSliderIds);
    }

    public function testEnrichWithMappedConfigLoadsItemsFromResolverResult(): void
    {
        $productMediaCollection = $this->getProductMediaCollection();
        $resolverContext = $this->getResolverContextWithoutMedia();
        $result = new ElementDataCollection();
        $result->add('product_media_id', new EntitySearchResult(
            'product_media',
            5,
            $productMediaCollection,
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $sliderItems = $imageSliderStruct->getSliderItems();
        static::assertIsArray($sliderItems);
        static::assertCount(5, $sliderItems);

        $expectedSliderIds = ['media0', 'media1', 'media2', 'media3', 'media4'];
        $imageSliderIds = array_map(fn ($value) => $value->getMedia()?->getId() ?? '', $sliderItems);
        static::assertSame($expectedSliderIds, $imageSliderIds);
    }

    public function testEnrichWithStaticConfig(): void
    {
        $media = new MediaEntity();
        $media->setId('media123');

        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());
        $result = new ElementDataCollection();
        $result->add('media_id', new EntitySearchResult(
            'media',
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $medias = [
            ['mediaId' => 'media123'],
        ];

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_STATIC, $medias));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $imageSliderItems = $imageSliderStruct->getSliderItems();
        static::assertIsArray($imageSliderItems);
        static::assertNotEmpty($imageSliderItems);

        $firstSliderItem = $imageSliderItems[0];
        static::assertSame($media->getId(), $firstSliderItem->getMedia()?->getId());
    }

    public function testEnrichWithMappedConfigAndHasProductCoverAtFirstPosition(): void
    {
        $productMediaCollection = $this->getProductMediaCollection();
        $resolverContext = $this->getResolverContext($productMediaCollection, 'media2');
        $result = $this->getEntitySearchResult($productMediaCollection, $resolverContext);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $sliderItems = $imageSliderStruct->getSliderItems();
        static::assertIsArray($sliderItems);

        // Cover image appears at first position
        $expectedSliderIds = ['media2', 'media0', 'media1', 'media3', 'media4'];
        $imageSliderIds = array_map(static fn ($value) => $value->getMedia()?->getId() ?? '', $sliderItems);

        static::assertSame($expectedSliderIds, $imageSliderIds);
    }

    public function testEnrichWithDefaultConfig(): void
    {
        $productMediaCollection = $this->getProductMediaCollection();
        $resolverContext = $this->getResolverContext($productMediaCollection);

        $this->publicFilesystem->write('/bundles/core/assets/default/cms/animated.gif', '');
        $this->publicFilesystem->write('/bundles/core/assets/default/cms/shopware.jpg', '');

        $medias = [
            ['fileName' => 'bundles/core/assets/default/cms/animated.gif'],
            ['fileName' => 'bundles/core/assets/default/cms/shopware.jpg'],
        ];

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_DEFAULT, $medias));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $result = $this->getEntitySearchResult($productMediaCollection, $resolverContext);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);
        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $imageSliderItems = $imageSliderStruct->getSliderItems() ?? [];
        static::assertCount(2, $imageSliderItems);

        $firstSliderItem = $imageSliderItems[0];
        $firstSliderItemMedia = $firstSliderItem->getMedia();
        static::assertInstanceOf(MediaEntity::class, $firstSliderItemMedia);
        static::assertSame('animated', $firstSliderItemMedia->getFileName());
        static::assertSame('image/gif', $firstSliderItemMedia->getMimeType());
        static::assertSame('gif', $firstSliderItemMedia->getFileExtension());

        $secondSliderItem = $imageSliderItems[1];
        $secondSliderItemMedia = $secondSliderItem->getMedia();
        static::assertInstanceOf(MediaEntity::class, $secondSliderItemMedia);
        static::assertSame('shopware', $secondSliderItemMedia->getFileName());
        static::assertSame('image/jpeg', $secondSliderItemMedia->getMimeType());
        static::assertSame('jpg', $secondSliderItemMedia->getFileExtension());
    }

    public function testEnrichWithCoverIdButWithoutCoverMedia(): void
    {
        $productMediaCollection = $this->getProductMediaCollection();
        $resolverContext = $this->getResolverContext($productMediaCollection, 'nonexistent-media');
        $result = $this->getEntitySearchResult($productMediaCollection, $resolverContext);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('sliderItems', FieldConfig::SOURCE_MAPPED, 'product.media'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->imageSliderResolver->enrich($slot, $resolverContext, $result);

        $imageSliderStruct = $slot->getData();
        static::assertInstanceOf(ImageSliderStruct::class, $imageSliderStruct);

        $sliderItems = $imageSliderStruct->getSliderItems();
        static::assertIsArray($sliderItems);

        // Cover image appears at first position
        $expectedSliderIds = ['media0', 'media1', 'media2', 'media3', 'media4'];
        $imageSliderIds = array_map(static fn ($value) => $value->getMedia()?->getId() ?? '', $sliderItems);

        static::assertSame($expectedSliderIds, $imageSliderIds);
    }

    protected function getProductMediaCollection(): ProductMediaCollection
    {
        $productMedia = [];
        for ($i = 0; $i <= 4; ++$i) {
            $mediaId = 'media' . $i;
            $mediaEntity = new MediaEntity();
            $mediaEntity->setId($mediaId);

            $tempProductMedia = new ProductMediaEntity();
            $tempProductMedia->setId($mediaId);
            $tempProductMedia->setMediaId($mediaId);
            $tempProductMedia->setMedia($mediaEntity);
            $tempProductMedia->setPosition($i);

            $productMedia[] = $tempProductMedia;
        }

        return new ProductMediaCollection($productMedia);
    }

    protected function getResolverContext(ProductMediaCollection $productMediaCollection, ?string $coverId = null): EntityResolverContext
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer_01');

        $product = new SalesChannelProductEntity();
        $product->setId('product_01');
        $product->setManufacturer($manufacturer);
        $product->setMedia($productMediaCollection);

        if ($coverId) {
            $product->setCoverId($coverId);
        }

        if (\is_string($coverId) && $productMediaCollection->has($coverId)) {
            $cover = new ProductMediaEntity();
            $cover->setId($coverId);
            $product->setCover($cover);
        }

        return new EntityResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
            static::getContainer()->get(SalesChannelProductDefinition::class),
            $product
        );
    }

    protected function getResolverContextWithoutMedia(): EntityResolverContext
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer_01');

        $product = new SalesChannelProductEntity();
        $product->setId('product_01');
        $product->setManufacturer($manufacturer);

        return new EntityResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
            static::getContainer()->get(SalesChannelProductDefinition::class),
            $product
        );
    }

    protected function getProductMediaCollectionWithoutAssociatedMedia(): ProductMediaCollection
    {
        $productMedia = [];
        for ($i = 0; $i <= 1; ++$i) {
            $mediaId = 'media' . $i;

            $tempProductMedia = new ProductMediaEntity();
            $tempProductMedia->setId($mediaId);
            $tempProductMedia->setMediaId($mediaId);
            $tempProductMedia->setPosition($i);

            $productMedia[] = $tempProductMedia;
        }

        return new ProductMediaCollection($productMedia);
    }

    protected function getEntitySearchResult(ProductMediaCollection $productMediaCollection, EntityResolverContext $resolverContext): ElementDataCollection
    {
        $result = new ElementDataCollection();
        $result->add('media_id', new EntitySearchResult(
            'media',
            5,
            $productMediaCollection->getMedia(),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        return $result;
    }
}
