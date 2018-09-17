<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Metadata\MetadataLoader;

use phpDocumentor\Reflection\Types\Self_;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\CanNotLoadMetadataException;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\PdfParserLoader;

class PdfParserLoaderTest extends TestCase
{
    public function testJpg()
    {
        $this->expectException(CanNotLoadMetadataException::class);

        $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/shopware-logo.png');
    }

    public function testIso()
    {
        $this->expectException(CanNotLoadMetadataException::class);

        $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/test.iso');
    }

    public function testPdf()
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/Shopware_5_3_Broschuere.pdf');

        self::assertCount(6, $result);
    }

    public function testLargePdf()
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
        self::assertNotFalse($tempFile, 'Failed to create a temp file');

        copy($fixturePdf, $tempFile);

        $fileSize = 256000000; // 265mb

        $fileHandle = fopen($tempFile, 'r+');
        ftruncate($fileHandle, $fileSize);
        fclose($fileHandle);

        self::assertEquals($fileSize, filesize($tempFile), 'Failed to inflate test pdf');

        return $tempFile;
    }

    private function getMetadataLoader(): PdfParserLoader
    {
        return new PdfParserLoader();
    }
}
