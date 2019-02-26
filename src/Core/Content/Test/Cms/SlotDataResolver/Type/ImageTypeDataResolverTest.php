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
        static::assertEquals('image', $this->imageResolver->getType());
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

        static::assertNull($criteriaCollection);
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

        static::assertCount(1, $criteriaCollection);

        $expectedCriteria = new Criteria(['media123']);

        $mediaCriteria = $criteriaCollection->all()[MediaDefinition::class]['media'];

        static::assertEquals($expectedCriteria, $mediaCriteria);
    }

    public function testEnrichWithEmptyConfig(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $request = new InternalRequest();
        $result = new SlotDataResolveResult();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig([]);

        $this->imageResolver->enrich($slot, $request, $context, $result);

        static::assertInstanceOf(ImageStruct::class, $slot->getData());
        static::assertEmpty($slot->getData()->getUrl());
        static::assertEmpty($slot->getData()->getMedia());
        static::assertEmpty($slot->getData()->getMediaId());
    }

    public function testEnrichWithUrlOnly(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $request = new InternalRequest();
        $result = new SlotDataResolveResult();

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['url' => 'http://shopware.com/image.jpg']);

        $this->imageResolver->enrich($slot, $request, $context, $result);

        static::assertInstanceOf(ImageStruct::class, $slot->getData());
        static::assertEquals('http://shopware.com/image.jpg', $slot->getData()->getUrl());
        static::assertEmpty($slot->getData()->getMedia());
        static::assertEmpty($slot->getData()->getMediaId());
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

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['mediaId' => 'media123']);

        $this->imageResolver->enrich($slot, $request, $context, $result);

        static::assertInstanceOf(ImageStruct::class, $slot->getData());
        static::assertEmpty($slot->getData()->getUrl());
        static::assertInstanceOf(MediaEntity::class, $slot->getData()->getMedia());
        static::assertEquals('media123', $slot->getData()->getMediaId());
        static::assertEquals($media, $slot->getData()->getMedia());
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

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['mediaId' => 'media123', 'url' => 'http://shopware.com/image.jpg']);

        $this->imageResolver->enrich($slot, $request, $context, $result);

        static::assertInstanceOf(ImageStruct::class, $slot->getData());
        static::assertEquals('http://shopware.com/image.jpg', $slot->getData()->getUrl());
        static::assertInstanceOf(MediaEntity::class, $slot->getData()->getMedia());
        static::assertEquals('media123', $slot->getData()->getMediaId());
        static::assertEquals($media, $slot->getData()->getMedia());
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

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('image');
        $slot->setConfig(['mediaId' => 'media123']);

        $this->imageResolver->enrich($slot, $request, $context, $result);

        static::assertInstanceOf(ImageStruct::class, $slot->getData());
        static::assertEmpty($slot->getData()->getUrl());
        static::assertEquals('media123', $slot->getData()->getMediaId());
        static::assertEmpty($slot->getData()->getMedia());
    }
}
