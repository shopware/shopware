<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\DocumentV2\Type\AppDocumentTypeConfig;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentFeatureDefinition;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DocumentFeatureDefinition::class)]
class DocumentFeatureDefinitionTest extends TestCase
{
    private DocumentFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new DocumentFeatureDefinition();
    }

    public function testGetTypeReturnsDocument(): void
    {
        static::assertSame('document', $this->definition->getType());
    }

    public function testGetConfigClassReturnsAppDocumentTypeConfig(): void
    {
        static::assertSame(AppDocumentTypeConfig::class, $this->definition->getConfigClass());
    }

    public function testFromAppMapsDeclaredDocumentTypesAndBackfillsMissingDefaultLocale(): void
    {
        $manifest = $this->manifest($this->documentTypes());

        $configs = $this->definition->fromApp($manifest, new Filesystem(''), 'en-GB');
        static::assertCount(2, $configs);

        $warranty = $configs[0];
        static::assertSame('swag_warranty', $warranty->getName());
        static::assertSame(['html', 'pdf'], $warranty->getFormats());
        static::assertSame(['en-GB' => 'Warranty certificate', 'de-DE' => 'Garantieschein'], $warranty->getLabel());
        static::assertSame(
            [
                'pageSize' => 'A4',
                'itemsPerPage' => 10,
                'displayHeader' => true,
                'displayFooter' => false,
                'filenamePrefix' => 'warranty',
            ],
            $warranty->getConfig()
        );

        $certificate = $configs[1];
        static::assertSame('swag_certificate', $certificate->getName());
        static::assertSame(['de-DE' => 'Zertifikat', 'en-GB' => 'Zertifikat'], $certificate->getLabel());
        static::assertSame([], $certificate->getConfig());
    }

    public function testFromAppBackfillsDefaultLocaleFromEnglishTranslationWhenShopDefaultDiffers(): void
    {
        $manifest = $this->manifest($this->documentTypes());

        $configs = $this->definition->fromApp($manifest, new Filesystem(''), 'fr-FR');
        $warranty = $configs[0];

        static::assertSame('swag_warranty', $warranty->getName());
        static::assertSame(
            ['en-GB' => 'Warranty certificate', 'de-DE' => 'Garantieschein', 'fr-FR' => 'Warranty certificate'],
            $warranty->getLabel()
        );
    }

    public function testFromAppReturnsEmptyListWhenManifestDeclaresNoDocuments(): void
    {
        $manifest = $this->manifest();

        static::assertSame([], $this->definition->fromApp($manifest, new Filesystem(''), 'en-GB'));
    }

    public function testToPayloadAndFromPayloadRoundTrip(): void
    {
        $config = new AppDocumentTypeConfig(
            'swag_warranty',
            ['html', 'pdf'],
            ['en-GB' => 'Warranty certificate', 'de-DE' => 'Garantieschein'],
            ['pageSize' => 'A4', 'itemsPerPage' => 10, 'displayHeader' => true]
        );

        $payload = $this->definition->toPayload($config, null);

        static::assertSame([
            'identifier' => 'swag_warranty',
            'formats' => ['html', 'pdf'],
            'label' => ['en-GB' => 'Warranty certificate', 'de-DE' => 'Garantieschein'],
            'config' => ['pageSize' => 'A4', 'itemsPerPage' => 10, 'displayHeader' => true],
        ], $payload);

        $restored = $this->definition->fromPayload($payload);

        static::assertSame($config->getName(), $restored->getName());
        static::assertSame($config->getFormats(), $restored->getFormats());
        static::assertSame($config->getLabel(), $restored->getLabel());
        static::assertSame($config->getConfig(), $restored->getConfig());
    }

    private function documentTypes(): string
    {
        return <<<'XML'
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
                    <label lang="de-DE">Zertifikat</label>
                    <formats>
                        <format>pdf</format>
                    </formats>
                </document-type>
            </documents>
            XML;
    }

    private function manifest(string $documents = ''): Manifest
    {
        return Manifest::createFromXml(<<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                      xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
                <meta>
                    <name>testDocumentFeature</name>
                    <label>Swag App Document Feature Test</label>
                    <author>shopware AG</author>
                    <copyright>(c) by shopware AG</copyright>
                    <version>1.0.0</version>
                    <license>MIT</license>
                </meta>
                {$documents}
            </manifest>
            XML);
    }
}
