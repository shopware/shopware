<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Metadata\MetadataLoader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\CanNotLoadMetadataException;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\GetId3Loader;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class GetId3LoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testJpg(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/shopware.jpg');

        self::assertCount(16, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testGif(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/logo.gif');

        self::assertCount(12, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testPng(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/shopware-logo.png');

        self::assertCount(12, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testSvg(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/logo-version-professionalplus.svg');

        self::assertCount(12, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testPdf(): void
    {
        $this->expectException(CanNotLoadMetadataException::class);

        $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/Shopware_5_3_Broschuere.pdf');
    }

    public function testMp4(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/small.mp4');

        self::assertCount(19, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testWebm(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/small.webm');

        self::assertCount(19, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testAvi(): void
    {
        $result = $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/small.avi');

        self::assertCount(19, $result, print_r($result, true));
        self::assertArrayNotHasKey('error', $result);
    }

    public function testDoc(): void
    {
        $this->expectException(CanNotLoadMetadataException::class);

        $this
            ->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/reader.doc');
    }

    public function testDocx(): void
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
