<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\TypeDetector;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\TypeDetector\DocumentTypeDetector;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class DocumentTypeDetectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testDetectGif(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo.gif'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectWebp(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware-logo.vp8x.webp'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectSvg(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/logo-version-professionalplus.svg'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectJpg(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware.jpg'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectPng(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/shopware-logo.png'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectDoc(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.doc'),
            null
        );

        static::assertInstanceOf(DocumentType::class, $type);
    }

    public function testDetectDocx(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/reader.docx'),
            null
        );

        static::assertInstanceOf(DocumentType::class, $type);
    }

    public function testDetectPdf(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.pdf'),
            null
        );

        static::assertInstanceOf(DocumentType::class, $type);
    }

    public function testDetectPdfDoesNotOverwrite(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.pdf'),
            new ImageType()
        );

        static::assertInstanceOf(ImageType::class, $type);
    }

    public function testDetectAvi(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.avi'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectMov(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mov'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectMp4(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.mp4'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectWebm(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/small.webm'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectIso(): void
    {
        $type = $this->getDocumentTypeDetector()->detect(
            $this->createMediaFile(__DIR__ . '/../fixtures/test.iso'),
            null
        );

        static::assertNull($type);
    }

    public function testDetectMp3(): void
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
