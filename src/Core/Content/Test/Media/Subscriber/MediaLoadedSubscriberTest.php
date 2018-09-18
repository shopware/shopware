<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailStruct;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaStruct;
use Shopware\Core\Content\Media\Subscriber\MediaLoadedSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MediaLoadedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testExtensionSubscribesToMediaLoaded(): void
    {
        static::assertCount(2, MediaLoadedSubscriber::getSubscribedEvents()['media.loaded']);
    }

    public function testItAddsUrl(): void
    {
        $subscriber = $this->getContainer()->get(MediaLoadedSubscriber::class);
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $mediaId = '34556f108ab14969a0dcf9d9522fd7df';
        $mimeType = 'image/png';

        $mediaStruct = new MediaStruct();
        $mediaStruct->setId($mediaId);
        $mediaStruct->setMimeType($mimeType);
        $mediaStruct->setFileExtension('png');

        $mediaLoadedEvent = new EntityLoadedEvent(MediaDefinition::class, new EntityCollection([$mediaStruct]), $context);
        $subscriber->addUrls($mediaLoadedEvent);

        static::assertEquals(
            'http://localhost/media/88/6c/ed/34556f108ab14969a0dcf9d9522fd7df.png',
            $mediaStruct->getUrl());
        static::assertEquals([], $mediaStruct->getThumbnails()->getElements());
    }

    public function testItAddsThumbnailUrl(): void
    {
        $subscriber = $this->getContainer()->get(MediaLoadedSubscriber::class);
        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $mediaId = '34556f108ab14969a0dcf9d9522fd7df';
        $mimeType = 'image/png';

        $thumbnailStruct = new MediaThumbnailStruct();
        $thumbnailStruct->setId($mediaId);
        $thumbnailStruct->setHeight(100);
        $thumbnailStruct->setWidth(100);
        $thumbnailStruct->setHighDpi(false);
        $mediaStruct = new MediaStruct();
        $mediaStruct->setId($mediaId);
        $mediaStruct->setMimeType($mimeType);
        $mediaStruct->setFileExtension('png');
        $mediaStruct->setThumbnails(new MediaThumbnailCollection([$thumbnailStruct]));

        $mediaLoadedEvent = new EntityLoadedEvent(MediaDefinition::class, new EntityCollection([$mediaStruct]), $context);
        $subscriber->addUrls($mediaLoadedEvent);

        static::assertEquals(
            'http://localhost/thumbnail/88/6c/ed/34556f108ab14969a0dcf9d9522fd7df_100x100.png',
            $mediaStruct->getThumbnails()->get($thumbnailStruct->getId())->getUrl());
    }
}
