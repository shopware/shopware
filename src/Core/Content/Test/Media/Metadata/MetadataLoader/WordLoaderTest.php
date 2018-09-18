<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Metadata\MetadataLoader;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\CanNotLoadMetadataException;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\WordLoader;

class WordLoaderTest extends TestCase
{
    public function testUnsupported(): void
    {
        $this->expectException(CanNotLoadMetadataException::class);

        $this->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/shopware-logo.png');
    }

    public function testDocx(): void
    {
        $metadata = $this->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/reader.docx');

        $this->assertCount(8, $metadata);
    }

    public function testDoc(): void
    {
        $metadata = $this->getMetadataLoader()
            ->extractMetadata(__DIR__ . '/../../fixtures/reader.doc');

        $this->assertCount(8, $metadata);
    }

    private function getMetadataLoader(): WordLoader
    {
        return new WordLoader();
    }
}
