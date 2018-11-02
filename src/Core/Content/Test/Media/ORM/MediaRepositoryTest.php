<?php declare(strict_types=1);

namespace src\Core\Content\Test\Media\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaRepository;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MediaRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const FIXTURE_FILE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var MediaRepository
     */
    private $mediaRepository;

    /**
     * @var Context
     */
    private $context;

    public function setUp()
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
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
                ],
            ],
            $this->context
        );
        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_META_INFO);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $mediaPath = $urlGenerator->getRelativeMediaUrl($mediaId, 'png');

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
        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_META_INFO);
        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_THUMBNAILS);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $mediaPath = $urlGenerator->getRelativeMediaUrl($mediaId, 'png');
        $thumbnailPath = $urlGenerator->getRelativeThumbnailUrl($mediaId, 'png', 100, 200, true);

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
            ],
            [
                'id' => $secondId,
                'name' => 'test media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
            ],
        ],
            $this->context
        );
        $this->context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_META_INFO);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $firstPath = $urlGenerator->getRelativeMediaUrl($firstId, 'png');
        $secondPath = $urlGenerator->getRelativeMediaUrl($secondId, 'png');

        $this->getPublicFilesystem()->putStream($firstPath, fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->putStream($secondPath, fopen(self::FIXTURE_FILE, 'r'));

        $this->mediaRepository->delete([['id' => $firstId]], $this->context);

        static::assertFalse($this->getPublicFilesystem()->has($firstPath));
        static::assertTrue($this->getPublicFilesystem()->has($secondPath));
    }
}
