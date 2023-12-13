<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\TypeDetector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\AudioType;
use Shopware\Core\Content\Media\MediaType\BinaryType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\VideoType;
use Shopware\Core\Content\Media\TypeDetector\DefaultTypeDetector;

/**
 * @internal
 */
#[CoversClass(DefaultTypeDetector::class)]
class DefaultTypeDetectorTest extends TestCase
{
    public function testDetectGif(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo.gif'),
            null
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectGifDoesntOverwrite(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo.gif'),
            new VideoType()
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectWebp(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware-logo.vp8x.webp'),
            null
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectAvif(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware-logo.avif'),
            null
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectSvg(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo-version-professionalplus.svg'),
            null
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectJpg(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware.jpg'),
            null
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectPng(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware-logo.png'),
            null
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectDoc(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.doc'),
            null
        );

        static::assertInstanceOf(BinaryType::class, $type);
    }

    public function testDetectDocx(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.docx'),
            null
        );

        static::assertInstanceOf(BinaryType::class, $type);
    }

    public function testDetectPdf(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.pdf'),
            null
        );

        static::assertInstanceOf(BinaryType::class, $type);
    }

    public function testDetectAvi(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.avi'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectMov(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mov'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectMp4(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mp4'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectWebm(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.webm'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectIso(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/test.iso'),
            null
        );

        static::assertInstanceOf(BinaryType::class, $type);
    }

    public function testDetectMp3(): void
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/file_example.mp3'),
            null
        );

        static::assertInstanceOf(AudioType::class, $type);
    }

    private function getDefaultTypeDetector(): DefaultTypeDetector
    {
        return new DefaultTypeDetector();
    }

    private function createMediaFile(string $filePath): MediaFile
    {
        static::assertIsString($mimeContentType = mime_content_type($filePath));
        static::assertIsInt($filesize = filesize($filePath));

        return new MediaFile(
            $filePath,
            $mimeContentType,
            pathinfo($filePath, \PATHINFO_EXTENSION),
            $filesize
        );
    }
}
