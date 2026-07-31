<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Document\Documents;
use Shopware\Core\Framework\App\Manifest\Xml\Document\DocumentType;
use Shopware\Core\Framework\App\Manifest\Xml\Document\DocumentTypeConfig;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Documents::class)]
#[CoversClass(DocumentType::class)]
#[CoversClass(DocumentTypeConfig::class)]
class DocumentsTest extends TestCase
{
    public function testParse(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testDocument/manifest.xml');

        static::assertNotNull($manifest->getDocuments());

        $documentTypes = $manifest->getDocuments()->getDocumentTypes();
        static::assertCount(2, $documentTypes);

        $documentType = $documentTypes[0];
        static::assertSame('swag_certificate', $documentType->getIdentifier());
        static::assertSame([
            'en-GB' => 'Certificate',
            'de-DE' => 'Zertifikat',
        ], $documentType->getLabel());
        static::assertSame(['html', 'pdf'], $documentType->getFormats());

        $config = $documentType->getConfig();
        static::assertSame('a4', $config['pageSize']);
        static::assertSame('portrait', $config['pageOrientation']);
        static::assertSame(10, $config['itemsPerPage']);
        static::assertTrue($config['displayHeader']);
        static::assertTrue($config['displayFooter']);
        static::assertFalse($config['displayPageCount']);
        static::assertTrue($config['displayLineItems']);
        static::assertFalse($config['displayPrices']);

        $documentTypeWithoutConfig = $documentTypes[1];
        static::assertSame('swag_certificate_no_config', $documentTypeWithoutConfig->getIdentifier());
        static::assertSame([], $documentTypeWithoutConfig->getConfig());
    }

    public function testGetDocumentsIsNullWhenNotDeclared(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');

        static::assertNull($manifest->getDocuments());
    }

    public function testUnknownFormatFailsSchemaValidation(): void
    {
        $this->expectException(AppException::class);

        Manifest::createFromXml($this->manifestWithDocumentType('<formats><format>bogus</format></formats>'));
    }

    public function testZugferdXmlFormatPassesSchemaValidation(): void
    {
        $manifest = Manifest::createFromXml(
            $this->manifestWithDocumentType('<formats><format>zugferd_xml</format></formats>'),
        );

        static::assertNotNull($manifest->getDocuments());
        static::assertSame(['zugferd_xml'], $manifest->getDocuments()->getDocumentTypes()[0]->getFormats());
    }

    public function testZugferdEmbeddedPdfFormatPassesSchemaValidation(): void
    {
        $manifest = Manifest::createFromXml(
            $this->manifestWithDocumentType('<formats><format>zugferd_embedded_pdf</format></formats>'),
        );

        static::assertNotNull($manifest->getDocuments());
        static::assertSame(['zugferd_embedded_pdf'], $manifest->getDocuments()->getDocumentTypes()[0]->getFormats());
    }

    public function testMissingFormatsElementFailsRequiredFieldValidation(): void
    {
        $this->expectExceptionObject(AppException::invalidArgument('formats must not be empty'));

        Manifest::createFromXml($this->manifestWithDocumentType(''));
    }

    private function manifestWithDocumentType(string $documentTypeBody): string
    {
        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
                <meta>
                    <name>testDocument</name>
                    <label>Swag App Documents Test</label>
                    <author>shopware AG</author>
                    <copyright>(c) by shopware AG</copyright>
                    <version>1.0.0</version>
                    <license>MIT</license>
                </meta>
                <documents>
                    <document-type>
                        <identifier>swag_certificate</identifier>
                        <label>Certificate</label>
                        {$documentTypeBody}
                    </document-type>
                </documents>
            </manifest>
            XML;
    }
}
