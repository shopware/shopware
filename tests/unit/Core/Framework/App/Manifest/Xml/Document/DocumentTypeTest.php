<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Document\DocumentType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DocumentType::class)]
class DocumentTypeTest extends TestCase
{
    public function testParsesIdentifierLabelAndAllSupportedFormats(): void
    {
        $documentType = $this->firstDocumentType();

        static::assertSame('swag_certificate', $documentType->getIdentifier());
        static::assertSame(
            ['en-GB' => 'Certificate', 'de-DE' => 'Zertifikat'],
            $documentType->getLabel()
        );
        static::assertSame(
            ['html', 'pdf', 'zugferd_xml', 'zugferd_embedded_pdf'],
            $documentType->getFormats()
        );
    }

    public function testConfigDefaultsToEmptyArrayWhenNotDeclared(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testDocument/manifest.xml');
        $documentType = $manifest->getDocuments()?->getDocumentTypes()[1] ?? null;

        static::assertNotNull($documentType);
        static::assertSame('swag_certificate_no_config', $documentType->getIdentifier());
        static::assertSame([], $documentType->getConfig());
    }

    public function testToArrayBackfillsSystemDefaultLanguageLabel(): void
    {
        $manifest = Manifest::createFromXml($this->manifestWithDocumentType(
            '<formats><format>html</format></formats>',
            '<label lang="de-DE">Zertifikat</label>'
        ));
        $documentType = $manifest->getDocuments()?->getDocumentTypes()[0] ?? null;
        static::assertNotNull($documentType);

        $data = $documentType->toArray('en-GB');

        static::assertSame(
            ['de-DE' => 'Zertifikat', 'en-GB' => 'Zertifikat'],
            $data['label'],
        );
    }

    public function testUnknownFormatFailsSchemaValidation(): void
    {
        $this->expectException(AppException::class);

        Manifest::createFromXml($this->manifestWithDocumentType('<formats><format>bogus</format></formats>'));
    }

    public function testMissingFormatsElementFailsRequiredFieldValidation(): void
    {
        $this->expectExceptionObject(AppException::invalidArgument('formats must not be empty'));

        Manifest::createFromXml($this->manifestWithDocumentType(''));
    }

    private function firstDocumentType(): DocumentType
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testDocument/manifest.xml');
        $documentType = $manifest->getDocuments()?->getDocumentTypes()[0] ?? null;

        static::assertNotNull($documentType);

        return $documentType;
    }

    private function manifestWithDocumentType(string $documentTypeBody, string $label = '<label>Certificate</label>'): string
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
                        {$label}
                        {$documentTypeBody}
                    </document-type>
                </documents>
            </manifest>
            XML;
    }
}
