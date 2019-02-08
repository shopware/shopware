<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Cms\SlotDataResolver\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\SlotDataResolver\SlotDataResolveResult;
use Shopware\Core\Content\Cms\SlotDataResolver\Type\ImageTypeDataResolver;
use Shopware\Core\Content\Cms\Storefront\Struct\ImageStruct;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Routing\InternalRequest;

class ImageTypeDataResolverTest extends TestCase
{
    /**
     * @var ImageTypeDataResolver
     */
    private $imageResolver;

    protected function setUp(): void
    {
        $this->imageResolver = new ImageTypeDataResolver();
    }

    public function testType(): void
    {
        $this->assertEquals('image', $this->imageResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $request = new InternalRequest();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig([]);

        $criteriaCollection = $this->imageResolver->collect($slot, $request, $context);

        $this->assertNull($criteriaCollection);
    }

    public function testCollectWithMediaId(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $request = new InternalRequest();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['mediaId' => 'media123']);

        $criteriaCollection = $this->imageResolver->collect($slot, $request, $context);

        $this->assertCount(1, $criteriaCollection);

        $expectedCriteria = new Criteria(['media123']);

        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media'];

        $this->assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $request = new InternalRequest();
        $result = new SlotDataResolveResult();

        $rawSlot = new CmsSlotEntity();
        $rawSlot->setUniqueIdentifier('id');
        $rawSlot->setType('image');
        $rawSlot->setConfig([]);

        /** @var ImageStruct $slot */
        $slot = $this->imageResolver->enrich($rawSlot, $request, $context, $result);

        $this->assertInstanceOf(ImageStruct::class, $slot);
        $this->assertEmpty($slot->getUrl());
        $this->assertEmpty($slot->getMedia());
        $this->assertEmpty($slot->getMediaId());
    }

    public function testEnrichWithUrlOnly(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $request = new InternalRequest();
        $result = new SlotDataResolveResult();

        $rawSlot = new CmsSlotEntity();
        $rawSlot->setUniqueIdentifier('id');
        $rawSlot->setType('image');
        $rawSlot->setConfig(['url' => 'http://shopware.com/image.jpg']);

        /** @var ImageStruct $slot */
        $slot = $this->imageResolver->enrich($rawSlot, $request, $context, $result);

        $this->assertInstanceOf(ImageStruct::class, $slot);
        $this->assertEquals('http://shopware.com/image.jpg', $slot->getUrl());
        $this->assertEmpty($slot->getMedia());
        $this->assertEmpty($slot->getMediaId());
    }

    public function testEnrichWithMediaOnly(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $request = new InternalRequest();

        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $mediaSearchResult = new EntitySearchResult(
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new SlotDataResolveResult();
        $result->add('media', $mediaSearchResult);

        $rawSlot = new CmsSlotEntity();
        $rawSlot->setUniqueIdentifier('id');
        $rawSlot->setType('image');
        $rawSlot->setConfig(['mediaId' => 'media123']);

        /** @var ImageStruct $slot */
        $slot = $this->imageResolver->enrich($rawSlot, $request, $context, $result);

        $this->assertInstanceOf(ImageStruct::class, $slot);
        $this->assertEmpty($slot->getUrl());
        $this->assertInstanceOf(MediaEntity::class, $slot->getMedia());
        $this->assertEquals('media123', $slot->getMediaId());
        $this->assertEquals($media, $slot->getMedia());
    }

    public function testEnrichWithMediaAndUrl(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $request = new InternalRequest();

        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $mediaSearchResult = new EntitySearchResult(
            1,
            new MediaCollection([$media]),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new SlotDataResolveResult();
        $result->add('media', $mediaSearchResult);

        $rawSlot = new CmsSlotEntity();
        $rawSlot->setUniqueIdentifier('id');
        $rawSlot->setType('image');
        $rawSlot->setConfig(['mediaId' => 'media123', 'url' => 'http://shopware.com/image.jpg']);

        /** @var ImageStruct $slot */
        $slot = $this->imageResolver->enrich($rawSlot, $request, $context, $result);

        $this->assertInstanceOf(ImageStruct::class, $slot);
        $this->assertEquals('http://shopware.com/image.jpg', $slot->getUrl());
        $this->assertInstanceOf(MediaEntity::class, $slot->getMedia());
        $this->assertEquals('media123', $slot->getMediaId());
        $this->assertEquals($media, $slot->getMedia());
    }

    public function testEnrichWithMissingMediaId(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $request = new InternalRequest();

        $media = new MediaEntity();
        $media->setUniqueIdentifier('media123');

        $mediaSearchResult = new EntitySearchResult(
            0,
            new MediaCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $result = new SlotDataResolveResult();
        $result->add('media', $mediaSearchResult);

        $rawSlot = new CmsSlotEntity();
        $rawSlot->setUniqueIdentifier('id');
        $rawSlot->setType('image');
        $rawSlot->setConfig(['mediaId' => 'media123']);

        /** @var ImageStruct $slot */
        $slot = $this->imageResolver->enrich($rawSlot, $request, $context, $result);

        $this->assertInstanceOf(ImageStruct::class, $slot);
        $this->assertEmpty($slot->getUrl());
        $this->assertEquals('media123', $slot->getMediaId());
        $this->assertEmpty($slot->getMedia());
    }
}
