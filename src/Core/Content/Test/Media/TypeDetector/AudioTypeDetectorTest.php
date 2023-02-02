<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\TypeDetector;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\AudioType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\TypeDetector\AudioTypeDetector;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class AudioTypeDetectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDetectGif(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo.gif'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectWebp(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware-logo.vp8x.webp'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectSvg(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo-version-professionalplus.svg'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectJpg(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware.jpg'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectPng(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware-logo.png'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectDoc(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.doc'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectDocx(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.docx'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectPdf(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.pdf'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectAvi(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.avi'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectMov(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mov'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectMp4(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mp4'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectWebm(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.webm'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectIso(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/test.iso'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectMp3(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/file_example.mp3'),
            null
        );

        static::assertInstanceOf(AudioType::class, $type);
    }

    public function testDetectMp3DoesNotOverwrite(): void
    {
        $type = $this->getAudioTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/file_example.mp3'),
            new ImageType()
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    private function getAudioTypeDetector(): AudioTypeDetector
    {
        return $this->getContainer()->get(AudioTypeDetector::class);
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
