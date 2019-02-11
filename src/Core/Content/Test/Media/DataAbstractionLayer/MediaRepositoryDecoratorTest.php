<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MediaRepositoryDecoratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURE_FILE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var Context
     */
    private $context;

    public function setUp()
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testDeleteMediaEntityWithoutThumbnails()
    {
        $mediaId = Uuid::uuid4()->getHex();

        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);

        $this->mediaRepository->create([
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                ],
            ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);

        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_META_INFO);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $mediaPath = $urlGenerator->getRelativeMediaUrl($media);

        $this->getPublicFilesystem()->putStream($mediaPath, fopen(self::FIXTURE_FILE, 'r'));

        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);

        static::assertFalse($this->getPublicFilesystem()->has($mediaPath));
    }

    public function testDeleteMediaEntityWithThumbnails()
    {
        $mediaId = Uuid::uuid4()->getHex();
        $this->context->getWriteProtection()->allow(
            MediaProtectionFlags::WRITE_META_INFO,
            MediaProtectionFlags::WRITE_THUMBNAILS
        );

        $this->mediaRepository->create([
            [
                'id' => $mediaId,
                'name' => 'test media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => $mediaId . '-' . (new \DateTime())->getTimestamp(),
                'thumbnails' => [
                    [
                        'width' => 100,
                        'height' => 200,
                        'highDpi' => true,
                    ],
                ],
            ],
        ],
            $this->context
        );
        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $this->context)->get($mediaId);

        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_META_INFO);
        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_THUMBNAILS);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $mediaPath = $urlGenerator->getRelativeMediaUrl($media);
        $thumbnailPath = $urlGenerator->getRelativeThumbnailUrl($media, 100, 200, true);

        $this->getPublicFilesystem()->putStream($mediaPath, fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->putStream($thumbnailPath, fopen(self::FIXTURE_FILE, 'r'));

        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);

        static::assertFalse($this->getPublicFilesystem()->has($mediaPath));
        static::assertFalse($this->getPublicFilesystem()->has($thumbnailPath));
    }

    public function testDeleteMediaDeletesOnlyFilesForGivenMediaId()
    {
        $firstId = Uuid::uuid4()->getHex();
        $secondId = Uuid::uuid4()->getHex();

        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $this->mediaRepository->create([
            [
                'id' => $firstId,
                'name' => 'test media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => $firstId . '-' . (new \DateTime())->getTimestamp(),
            ],
            [
                'id' => $secondId,
                'name' => 'test media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => $secondId . '-' . (new \DateTime())->getTimestamp(),
            ],
        ],
            $this->context
        );
        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_META_INFO);

        $read = $this->mediaRepository->search(
            new Criteria(
                [
                    $firstId,
                    $secondId,
                ]
            ),
            $this->context
        );
        $firstMedia = $read->get($firstId);
        $secondMedia = $read->get($secondId);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $firstPath = $urlGenerator->getRelativeMediaUrl($firstMedia);
        $secondPath = $urlGenerator->getRelativeMediaUrl($secondMedia);

        $this->getPublicFilesystem()->putStream($firstPath, fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->putStream($secondPath, fopen(self::FIXTURE_FILE, 'r'));

        $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        static::assertFalse($this->getPublicFilesystem()->has($firstPath));
        static::assertTrue($this->getPublicFilesystem()->has($secondPath));
    }

    public function testDeleteForUnusedIds()
    {
        $firstId = Uuid::uuid4()->getHex();

        $event = $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        static::assertNull($event->getEventByDefinition(MediaDefinition::class));
    }

    public function testDeleteForMediaWithoutFile()
    {
        $firstId = Uuid::uuid4()->getHex();

        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $this->mediaRepository->create([
            [
                'id' => $firstId,
                'name' => 'test media',
            ],
        ],
            $this->context
        );
        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_META_INFO);

        $event = $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        static::assertCount(1, $event->getEventByDefinition(MediaDefinition::class)->getIds());
        static::assertEquals($firstId, $event->getEventByDefinition(MediaDefinition::class)->getIds()[0]);
    }

    public function testDeleteWithAlreadyDeletedFile()
    {
        $firstId = Uuid::uuid4()->getHex();

        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $this->mediaRepository->create([
            [
                'id' => $firstId,
                'name' => 'test media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'fileName' => $firstId . '-' . (new \DateTime())->getTimestamp(),
            ],
        ],
            $this->context
        );
        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_META_INFO);

        $event = $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        static::assertCount(1, $event->getEventByDefinition(MediaDefinition::class)->getIds());
        static::assertEquals($firstId, $event->getEventByDefinition(MediaDefinition::class)->getIds()[0]);
    }
}
