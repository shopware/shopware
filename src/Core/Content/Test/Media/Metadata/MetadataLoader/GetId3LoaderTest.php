<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Metadata\MetadataLoader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\CanNotLoadMetadataException;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\GetId3Loader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class GetId3LoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testJpg()
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/shopware.jpg');

        self::assertCount(16, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testGif()
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/logo.gif');

        self::assertCount(12, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testPng()
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/shopware-logo.png');

        self::assertCount(12, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testSvg()
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/logo-version-professionalplus.svg');

        self::assertCount(12, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testPdf()
    {
        $this->expectException(CanNotLoadMetadataException::class);

        $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/Shopware_5_3_Broschuere.pdf');
    }

    public function testMp4()
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/small.mp4');

        self::assertCount(19, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testWebm()
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/small.webm');

        self::assertCount(19, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testAvi()
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/small.avi');

        self::assertCount(19, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testDoc()
    {
        $this->expectException(CanNotLoadMetadataException::class);

        $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/reader.doc');
    }

    public function testDocx()
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/reader.docx');

        self::assertSame('zip.msoffice', $result['fileformat']);
    }

    private function getMetadataLoader(): GetId3Loader
    {
        return new GetId3Loader();
    }
}
