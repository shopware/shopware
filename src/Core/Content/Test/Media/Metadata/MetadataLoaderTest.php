<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Metadata;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\VideoType;
use Shopware\Core\Content\Media\Metadata\Metadata;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Metadata\Type\DocumentMetadata;
use Shopware\Core\Content\Media\Metadata\Type\ImageMetadata;
use Shopware\Core\Content\Media\Metadata\Type\VideoMetadata;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MetadataLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testJpg(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->loadFromFile($this->createMediaFile(__DIR__ . '/../fixtures/shopware.jpg'), new ImageType());

        static::assertCount(1, $result->getRawMetadata(), print_r($result, true));
        static::assertArrayNotHasKey('error', $result->getRawMetadata());
        $this->assertImageMetadata($result, 1530, 1021);
    }

    public function testGif(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->loadFromFile($this->createMediaFile(__DIR__ . '/../fixtures/logo.gif'), new ImageType());

        static::assertCount(1, $result->getRawMetadata(), print_r($result, true));
        static::assertArrayNotHasKey('error', $result->getRawMetadata());
        $this->assertImageMetadata($result, 142, 37);
    }

    public function testPng(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->loadFromFile($this->createMediaFile(__DIR__ . '/../fixtures/shopware-logo.png'), new ImageType());

        static::assertCount(1, $result->getRawMetadata(), print_r($result, true));
        static::assertArrayNotHasKey('error', $result->getRawMetadata());
        $this->assertImageMetadata($result, 499, 266);
    }

    public function testSvg(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->loadFromFile($this->createMediaFile(__DIR__ . '/../fixtures/logo-version-professionalplus.svg'), new ImageType());

        static::assertCount(1, $result->getRawMetadata(), print_r($result, true));
        static::assertArrayNotHasKey('error', $result->getRawMetadata());
        $this->assertImageMetadata($result);
    }

    public function testPdf(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->loadFromFile($this->createMediaFile(__DIR__ . '/../fixtures/Shopware_5_3_Broschuere.pdf'), new DocumentType());

        static::assertCount(1, $result->getRawMetadata(), print_r($result, true));
        static::assertArrayNotHasKey('error', $result->getRawMetadata(), print_r($result, true));
        $this->assertDocumentMetadata($result, 19, 'Adobe InDesign CC 13.0 (Macintosh)');
    }

    public function testMp4(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->loadFromFile($this->createMediaFile(__DIR__ . '/../fixtures/small.mp4'), new VideoType());

        static::assertCount(1, $result->getRawMetadata(), print_r($result, true));
        static::assertArrayNotHasKey('error', $result->getRawMetadata());

        $this->assertImageMetadata($result, 560, 320);
        $this->assertVideoMetadata($result, 30.0);
    }

    public function testWebm(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->loadFromFile($this->createMediaFile(__DIR__ . '/../fixtures/small.webm'), new VideoType());

        static::assertCount(1, $result->getRawMetadata(), print_r($result, true));
        static::assertArrayNotHasKey('error', $result->getRawMetadata());

        $this->assertImageMetadata($result, 560, 320);
        $this->assertVideoMetadata($result, 30.0);
    }

    public function testAvi(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->loadFromFile($this->createMediaFile(__DIR__ . '/../fixtures/small.avi'), new VideoType());

        static::assertCount(1, $result->getRawMetadata(), print_r($result, true));
        static::assertArrayNotHasKey('error', $result->getRawMetadata());

        $this->assertImageMetadata($result, 560, 320);
        $this->assertVideoMetadata($result, 30.0);
    }

    public function testDoc(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->loadFromFile($this->createMediaFile(__DIR__ . '/../fixtures/reader.doc'), new DocumentType());

        static::assertCount(1, $result->getRawMetadata(), print_r($result, true));
        $this->assertDocumentMetadata($result, null, '', '');
    }

    public function testDocx(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->loadFromFile($this->createMediaFile(__DIR__ . '/../fixtures/reader.docx'), new DocumentType());

        static::assertCount(2, $result->getRawMetadata(), print_r($result, true));
        $this->assertDocumentMetadata($result, null, 'PHPWord', 'A Word Document');
    }

    private function getMetadataLoader(): MetadataLoader
    {
        return $this->getContainer()
            ->get(MetadataLoader::class);
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

    private function assertImageMetadata(Metadata $result, ?int $width = null, ?int $height = null): void
    {
        $type = $result->getType();
        static::assertInstanceOf(ImageMetadata::class, $type);

        $this->getMetadataLoader()->updateMetadata($result);

        static::assertSame($width, $type->getWidth());
        static::assertSame($height, $type->getHeight());
    }

    private function assertVideoMetadata(Metadata $result, float $frameRate): void
    {
        $type = $result->getType();
        static::assertInstanceOf(VideoMetadata::class, $type);

        $this->getMetadataLoader()->updateMetadata($result);

        static::assertSame($frameRate, $type->getFrameRate());
    }

    private function assertDocumentMetadata(
        Metadata $result,
        ?int $pages = null,
        ?string $creator = null,
        ?string $title = null
    ): void {
        $type = $result->getType();
        static::assertInstanceOf(DocumentMetadata::class, $type);

        $this->getMetadataLoader()->updateMetadata($result);
        static::assertSame($pages, $type->getPages());
        static::assertSame($creator, $type->getCreator());
        static::assertSame($title, $type->getTitle());
    }
}
