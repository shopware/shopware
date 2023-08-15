<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Domain\Path\MediaUrlLoader;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('core')]
class MediaLoadedSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testLegacyUrlGenerationForMedia(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);
        $subscriber = $this->getContainer()->get(MediaUrlLoader::class);
        $context = Context::createDefaultContext();

        $mediaId = '34556f108ab14969a0dcf9d9522fd7df';
        $mimeType = 'image/png';

        $mediaEntity = new MediaEntity();
        $mediaEntity->setId($mediaId);
        $mediaEntity->setMimeType($mimeType);
        $mediaEntity->setFileExtension('png');
        $mediaEntity->setFileName($mediaId . '-134578345');
        $mediaEntity->setThumbnails(new MediaThumbnailCollection());

        $mediaLoadedEvent = new EntityLoadedEvent($this->getContainer()->get(MediaDefinition::class), [$mediaEntity], $context);
        $subscriber->legacy($mediaLoadedEvent);

        static::assertStringEndsWith(
            $mediaEntity->getFileName() . '.' . $mediaEntity->getFileExtension(),
            $mediaEntity->getUrl()
        );
        static::assertNotNull($mediaEntity->getThumbnails());
        static::assertEquals([], $mediaEntity->getThumbnails()->getElements());
    }

    public function testLegacyUrlGenerationForThumbnail(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $subscriber = $this->getContainer()->get(MediaUrlLoader::class);
        $context = Context::createDefaultContext();

        $mediaId = '34556f108ab14969a0dcf9d9522fd7df';
        $mimeType = 'image/png';

        $thumbnailEntity = new MediaThumbnailEntity();
        $thumbnailEntity->setId($mediaId);
        $thumbnailEntity->setHeight(100);
        $thumbnailEntity->setWidth(100);

        $mediaEntity = new MediaEntity();
        $mediaEntity->setId($mediaId);
        $mediaEntity->setMimeType($mimeType);
        $mediaEntity->setFileExtension('png');
        $mediaEntity->setFileName($mediaId . '-134578345');
        $mediaEntity->setThumbnails(new MediaThumbnailCollection([$thumbnailEntity]));

        $mediaLoadedEvent = new EntityLoadedEvent(
            $this->getContainer()->get(MediaDefinition::class),
            [$mediaEntity],
            $context
        );

        $subscriber->legacy($mediaLoadedEvent);

        static::assertNotNull($mediaEntity->getThumbnails());
        static::assertNotNull($mediaEntity->getThumbnails()->get($thumbnailEntity->getId()));

        static::assertStringEndsWith(
            $mediaEntity->getFileName() . '_100x100.' . $mediaEntity->getFileExtension(),
            $mediaEntity->getThumbnails()->get($thumbnailEntity->getId())->getUrl()
        );
    }
}
