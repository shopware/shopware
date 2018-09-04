<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Metadata\MetadataLoader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\CanNotLoadMetadataException;
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

    public function testPdf()
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/Shopware_5_3_Broschuere.pdf');

        self::assertCount(6, $result);
    }

    private function getMetadataLoader(): PdfParserLoader
    {
        return new PdfParserLoader();
    }
}
