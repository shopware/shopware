<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\TypeDetector;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\VideoType;
use Shopware\Core\Content\Media\TypeDetector\VideoTypeDetector;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class VideoTypeDetectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDetectGif()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo.gif'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectWebp()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware.webp'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectSvg()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo-version-professionalplus.svg'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectJpg()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware.jpg'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectPng()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware-logo.png'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectDoc()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.doc'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectDocx()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.docx'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectPdf()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/Shopware_5_3_Broschuere.pdf'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectAvi()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.avi'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectAviDoesNotOverwrite()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.avi'),
            new ImageType()
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectMov()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mov'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectMp4()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mp4'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectWebm()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.webm'),
            null
        );

        static::assertInstanceOf(VideoType::class, $type);
    }

    public function testDetectIso()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/test.iso'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectMp3()
    {
        $type = $this->getVideoTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/file_example.mp3'),
            null
        );

        static::assertNull($type);
    }

    private function getVideoTypeDetector(): VideoTypeDetector
    {
        return $this->getContainer()->get(VideoTypeDetector::class);
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
