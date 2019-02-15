<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Metadata\MetadataLoader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\CanNotLoadMetadataException;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\PdfParserLoader;

class PdfParserLoaderTest extends TestCase
{
    public function testJpg(): void
    {
        $this->expectException(CanNotLoadMetadataException::class);

        $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/shopware-logo.png');
    }

    public function testIso(): void
    {
        $this->expectException(CanNotLoadMetadataException::class);

        $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/test.iso');
    }

    public function testPdf(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/Shopware_5_3_Broschuere.pdf');

        static::assertCount(6, $result);
    }

    public function testLargePdf(): void
    {
        $this->expectException(CanNotLoadMetadataException::class);

        $largePdf = $this->getLargePdf();

        $this
            ->getMetadataLoader()
            ->extractMetadata($largePdf);
    }

    private function getLargePdf()
    {
        $fixturePdf = __DIR__ . '/../../fixtures/Shopware_5_3_Broschuere.pdf';

        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertNotFalse($tempFile, 'Failed to create a temp file');

        copy($fixturePdf, $tempFile);

        $fileSize = 256000000; // 265mb

        $fileHandle = fopen($tempFile, 'r+');
        ftruncate($fileHandle, $fileSize);
        fclose($fileHandle);

        static::assertEquals($fileSize, filesize($tempFile), 'Failed to inflate test pdf');

        return $tempFile;
    }

    private function getMetadataLoader(): PdfParserLoader
    {
        return new PdfParserLoader();
    }
}
