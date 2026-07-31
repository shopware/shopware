<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Document\Documents;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Documents::class)]
class DocumentsTest extends TestCase
{
    public function testGetDocumentTypesReturnsEveryDeclaredType(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testDocument/manifest.xml');

        $documents = $manifest->getDocuments();
        static::assertNotNull($documents);

        $documentTypes = $documents->getDocumentTypes();
        static::assertCount(2, $documentTypes);
        static::assertSame('swag_certificate', $documentTypes[0]->getIdentifier());
        static::assertSame('swag_certificate_no_config', $documentTypes[1]->getIdentifier());
    }

    public function testGetDocumentsIsNullWhenNotDeclared(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');

        static::assertNull($manifest->getDocuments());
    }
}
