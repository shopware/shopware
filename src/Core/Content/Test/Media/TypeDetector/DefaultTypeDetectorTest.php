<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\TypeDetector;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\AudioType;
use Shopware\Core\Content\Media\MediaType\BinaryType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\VideoType;
use Shopware\Core\Content\Media\TypeDetector\DefaultTypeDetector;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class DefaultTypeDetectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDetectGif()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo.gif'),
            null
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectGifDoesntOverwrite()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo.gif'),
            new VideoType()
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectWebp()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware.webp'),
            null
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectSvg()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo-version-professionalplus.svg'),
            null
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectJpg()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware.jpg'),
            null
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectPng()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware-logo.png'),
            null
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectDoc()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.doc'),
            null
        );

        static::assertInstanceOf(BinaryType::class, $type);
    }

    public function testDetectDocx()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.docx'),
            null
        );

        static::assertInstanceOf(BinaryType::class, $type);
    }

    public function testDetectPdf()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/Shopware_5_3_Broschuere.pdf'),
            null
        );

        static::assertInstanceOf(BinaryType::class, $type);
    }

    public function testDetectAvi()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.avi'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectMov()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mov'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectMp4()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mp4'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectWebm()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.webm'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectIso()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/test.iso'),
            null
        );

        static::assertInstanceOf(BinaryType::class, $type);
    }

    public function testDetectMp3()
    {
        $type = $this->getDefaultTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/file_example.mp3'),
            null
        );

        static::assertInstanceOf(AudioType::class, $type);
    }

    private function getDefaultTypeDetector(): DefaultTypeDetector
    {
        return $this->getContainer()->get(DefaultTypeDetector::class);
    }

    private function createMediaFile(string $filePath): MediaFile
    {
        return new MediaFile(
            $filePath,
            mime_content_type($filePath),
            pathinfo($filePath, PATHINFO_EXTENSION),
            filesize($filePath)
        );
    }
}
