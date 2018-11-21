<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\TypeDetector;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\TypeDetector\DocumentTypeDetector;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class DocumentTypeDetectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDetectGif()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo.gif'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectWebp()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware.webp'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectSvg()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo-version-professionalplus.svg'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectJpg()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware.jpg'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectPng()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware-logo.png'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectDoc()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.doc'),
            null
        );

        static::assertInstanceOf(DocumentType::class, $type);
    }

    public function testDetectDocx()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.docx'),
            null
        );

        static::assertInstanceOf(DocumentType::class, $type);
    }

    public function testDetectPdf()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/Shopware_5_3_Broschuere.pdf'),
            null
        );

        static::assertInstanceOf(DocumentType::class, $type);
    }

    public function testDetectPdfDoesNotOverwrite()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/Shopware_5_3_Broschuere.pdf'),
            new ImageType()
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectAvi()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.avi'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectMov()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mov'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectMp4()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mp4'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectWebm()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.webm'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectIso()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/test.iso'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectMp3()
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/file_example.mp3'),
            null
        );

        static::assertNull($type);
    }

    private function getDocumentTypeDetector(): DocumentTypeDetector
    {
        return $this->getContainer()->get(DocumentTypeDetector::class);
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
