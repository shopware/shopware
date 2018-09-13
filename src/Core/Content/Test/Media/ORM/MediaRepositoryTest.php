<?php declare(strict_types=1);

namespace src\Core\Content\Test\Media\ORM;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\ORM\MediaRepository;
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
        $this->mediaRepository = $this->getContainer()->get('media.repository');

        $mediaId = Uuid::uuid4()->getHex();

        $this->context->getExtension('write_protection')->set('write_media', true);
        $this->mediaRepository->create([
                [
                    'id' => $mediaId,
                    'name' => 'test media',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'hasFile' => true,
                ],
            ],
            $this->context
        );
        $this->context->getExtension('write_protection')->set('write_media', false);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $mediaPath = $urlGenerator->getRelativeMediaUrl($mediaId, 'png');

        $this->getPublicFilesystem()->putStream($mediaPath, fopen(self::FIXTURE_FILE, 'r'));

        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);

        static::assertFalse($this->getPublicFilesystem()->has($mediaPath));
    }

    public function testDeleteMediaEntityWithThumbnails()
    {
        $mediaId = Uuid::uuid4()->getHex();

        $this->context->getExtension('write_protection')->set('write_media', true);
        $this->context->getExtension('write_protection')->set('write_thumbnails', true);
        $this->mediaRepository->create([
            [
                'id' => $mediaId,
                'name' => 'test media',
                'mimeType' => 'image/png',
                'fileExtension' => 'png',
                'hasFile' => true,
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
        $this->context->getExtension('write_protection')->set('write_media', false);
        $this->context->getExtension('write_protection')->set('write_thumbnails', false);

        $urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
        $mediaPath = $urlGenerator->getRelativeMediaUrl($mediaId, 'png');
        $thumbnailPath = $urlGenerator->getRelativeThumbnailUrl($mediaId, 'png', 100, 200, true);

        $this->getPublicFilesystem()->putStream($mediaPath, fopen(self::FIXTURE_FILE, 'r'));
        $this->getPublicFilesystem()->putStream($thumbnailPath, fopen(self::FIXTURE_FILE, 'r'));

        $this->mediaRepository->delete([['id' => $mediaId]], $this->context);

        static::assertFalse($this->getPublicFilesystem()->has($mediaPath));
        static::assertFalse($this->getPublicFilesystem()->has($thumbnailPath));
    }
}
