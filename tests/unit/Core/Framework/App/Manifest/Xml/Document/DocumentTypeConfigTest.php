<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Document\DocumentTypeConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DocumentTypeConfig::class)]
class DocumentTypeConfigTest extends TestCase
{
    public function testParsesConfigWithCoercedScalarTypes(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testDocument/manifest.xml');
        $config = $manifest->getDocuments()?->getDocumentTypes()[0]->getConfig() ?? [];

        static::assertSame('a4', $config['pageSize']);
        static::assertSame('portrait', $config['pageOrientation']);
        static::assertSame(10, $config['itemsPerPage']);
        static::assertTrue($config['displayHeader']);
        static::assertTrue($config['displayFooter']);
        static::assertFalse($config['displayPageCount']);
        static::assertTrue($config['displayLineItems']);
        static::assertFalse($config['displayPrices']);
    }
}
