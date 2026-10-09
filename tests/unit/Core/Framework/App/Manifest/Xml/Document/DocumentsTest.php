<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Document;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
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
    public function testFromXmlParsesLabelFormatsAndConfigCoercion(): void
    {
        $manifest = $this->manifest();
        static::assertNotNull($manifest->getDocuments());

        $documentTypes = $manifest->getDocuments()->getDocumentTypes();
        static::assertCount(2, $documentTypes);

        $warranty = $documentTypes[0];
        static::assertSame('swag_warranty', $warranty['identifier']);
        static::assertSame(
            ['en-GB' => 'Warranty certificate', 'de-DE' => 'Garantieschein'],
            $warranty['label']
        );
        static::assertSame(['html', 'pdf'], $warranty['formats']);
        static::assertSame(
            [
                'pageSize' => 'A4',
                'itemsPerPage' => 10,
                'displayHeader' => true,
                'displayFooter' => false,
                'filenamePrefix' => 'warranty',
            ],
            $warranty['config']
        );
    }

    public function testFromXmlWithoutConfigYieldsEmptyConfigArray(): void
    {
        $manifest = $this->manifest();

        $documentTypes = $manifest->getDocuments()?->getDocumentTypes();
        static::assertNotNull($documentTypes);

        $certificate = $documentTypes[1];
        static::assertSame('swag_certificate', $certificate['identifier']);
        static::assertSame(['en-GB' => 'Certificate'], $certificate['label']);
        static::assertSame(['pdf'], $certificate['formats']);
        static::assertSame([], $certificate['config']);
    }

    public function testFromXmlRejectsADocumentTypeWithoutFormats(): void
    {
        $this->expectExceptionObject(AppException::invalidArgument('formats must not be empty'));

        Manifest::createFromXml(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
                <meta>
                    <name>testDocument</name>
                    <label>Swag App Document Test</label>
                    <author>shopware AG</author>
                    <copyright>(c) by shopware AG</copyright>
                    <version>1.0.0</version>
                    <license>MIT</license>
                </meta>
                <documents>
                    <document-type>
                        <identifier>swag_warranty</identifier>
                        <label>Warranty certificate</label>
                    </document-type>
                </documents>
            </manifest>
            XML);
    }

    private function manifest(): Manifest
    {
        return Manifest::createFromXml(<<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
                <meta>
                    <name>testDocument</name>
                    <label>Swag App Document Test</label>
                    <author>shopware AG</author>
                    <copyright>(c) by shopware AG</copyright>
                    <version>1.0.0</version>
                    <license>MIT</license>
                </meta>
                <documents>
                    <document-type>
                        <identifier>swag_warranty</identifier>
                        <label>Warranty certificate</label>
                        <label lang="de-DE">Garantieschein</label>
                        <formats>
                            <format>html</format>
                            <format>pdf</format>
                        </formats>
                        <config>
                            <page-size>A4</page-size>
                            <items-per-page>10</items-per-page>
                            <display-header>true</display-header>
                            <display-footer>false</display-footer>
                            <filename-prefix>warranty</filename-prefix>
                        </config>
                    </document-type>
                    <document-type>
                        <identifier>swag_certificate</identifier>
                        <label>Certificate</label>
                        <formats>
                            <format>pdf</format>
                        </formats>
                    </document-type>
                </documents>
            </manifest>
            XML);
    }
}
