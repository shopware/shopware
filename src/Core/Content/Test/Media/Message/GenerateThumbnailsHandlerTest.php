<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Message;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsHandler;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Shopware\Core\Content\Media\Message\UpdateThumbnailsMessage;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class GenerateThumbnailsHandlerTest extends TestCase
{
    use IntegrationTestBehaviour,
        MediaFixtures;

    private $urlGenerator;
    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;
    private $thumbnailRepository;
    private $context;
    /**
     * @var GenerateThumbnailsHandler
     */
    private $handler;

    public function setUp(): void
    {
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->thumbnailRepository = $this->getContainer()->get('media_thumbnail.repository');
        $this->context = Context::createDefaultContext();
        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);

        $this->handler = $this->getContainer()->get(GenerateThumbnailsHandler::class);
    }

    public function testGenerateThumbnails(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_THUMBNAILS);
        $this->thumbnailRepository->create([
            [
                'mediaId' => $media->getId(),
                'width' => 987,
                'height' => 987,
            ],
            [
                'mediaId' => $media->getId(),
                'width' => 150,
                'height' => 150,
            ],
        ], $this->context);
        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_THUMBNAILS);
        $media = $this->mediaRepository->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());

        $this->getPublicFilesystem()->putStream(
            $this->urlGenerator->getRelativeMediaUrl($media),
            fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r')
        );

        $msg = new GenerateThumbnailsMessage();
        $msg->setMediaIds([$media->getId()]);
        $msg->withContext($this->context);

        $this->handler->__invoke($msg);

        $media = $this->mediaRepository->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());
        static::assertEquals(2, $media->getThumbnails()->count());

        $filteredThumbnails = $media->getThumbnails()->filter(function ($thumbnail) {
            return ($thumbnail->getWidth() === 300 && $thumbnail->getHeight() === 300) ||
                ($thumbnail->getWidth() === 150 && $thumbnail->getHeight() === 150);
        });

        static::assertEquals(2, $filteredThumbnails->count());
        /** @var MediaThumbnailEntity $thumbnail */
        foreach ($filteredThumbnails as $thumbnail) {
            $path = $this->urlGenerator->getRelativeThumbnailUrl($media, $thumbnail->getWidth(), $thumbnail->getHeight());
            static::assertTrue(
                $this->getPublicFilesystem()->has($path),
                'Thumbnail: ' . $path . ' does not exist');
        }
    }

    public function testUpdateThumbnails(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getPngWithFolder();

        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_THUMBNAILS);
        $this->thumbnailRepository->create([
            [
                'mediaId' => $media->getId(),
                'width' => 987,
                'height' => 987,
            ],
        ], $this->context);
        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_THUMBNAILS);
        $media = $this->mediaRepository->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());

        $this->getPublicFilesystem()->putStream(
            $this->urlGenerator->getRelativeMediaUrl($media),
            fopen(__DIR__ . '/../fixtures/shopware-logo.png', 'r')
        );

        $msg = new UpdateThumbnailsMessage();
        $msg->setMediaIds([$media->getId()]);
        $msg->withContext($this->context);

        $this->handler->__invoke($msg);

        $media = $this->mediaRepository->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());
        static::assertEquals(2, $media->getThumbnails()->count());

        $filteredThumbnails = $media->getThumbnails()->filter(function ($thumbnail) {
            return ($thumbnail->getWidth() === 300 && $thumbnail->getHeight() === 300) ||
                ($thumbnail->getWidth() === 150 && $thumbnail->getHeight() === 150);
        });

        static::assertEquals(2, $filteredThumbnails->count());
        /** @var MediaThumbnailEntity $thumbnail */
        foreach ($filteredThumbnails as $thumbnail) {
            $path = $this->urlGenerator->getRelativeThumbnailUrl($media, $thumbnail->getWidth(), $thumbnail->getHeight());
            static::assertTrue(
                $this->getPublicFilesystem()->has($path),
                'Thumbnail: ' . $path . ' does not exist');
        }
    }
}
