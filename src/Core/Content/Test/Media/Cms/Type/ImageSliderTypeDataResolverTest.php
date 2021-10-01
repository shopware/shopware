<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ImageSliderStruct;
use Shopware\Core\Content\Media\Cms\Type\ImageSliderTypeDataResolver;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaCollection;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class ImageSliderTypeDataResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ImageSliderTypeDataResolver
     */
    private $imageSliderResolver;

    protected function setUp(): void
    {
        $this->imageSliderResolver = new ImageSliderTypeDataResolver();
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
        static::assertCount(1, $criteriaCollection);

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
        /** @var ImageSliderStruct $imageSliderStruct */
        $imageSliderStruct = $slot->getData();

        for ($i = 0; $i < 5; ++$i) {
            static::assertEquals($imageSliderStruct->getSliderItems()[$i]->getMedia()->getId(), 'media' . $i);
        }
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
        static::assertSame($media->getId(), $firstSliderItem->getMedia()->getId());
    }

    protected function getProductMediaCollection(): ProductMediaCollection
    {
        $productMedia = [];
        for ($i = 4; $i >= 0; --$i) {
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

    protected function getResolverContext(ProductMediaCollection $productMediaCollection): EntityResolverContext
    {
        $manufacturer = new ProductManufacturerEntity();
        $manufacturer->setId('manufacturer_01');

        $product = new SalesChannelProductEntity();
        $product->setId('product_01');
        $product->setManufacturer($manufacturer);
        $product->setMedia($productMediaCollection);

        return new EntityResolverContext(
            $this->createMock(SalesChannelContext::class),
            new Request(),
            $this->getContainer()->get(SalesChannelProductDefinition::class),
            $product
        );
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
