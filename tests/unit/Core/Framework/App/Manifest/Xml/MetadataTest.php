<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Validation\Error\MissingTranslationError;

/**
 * @internal
 */
#[CoversClass(Metadata::class)]
class MetadataTest extends TestCase
{
    private Manifest $manifest;

    protected function setUp(): void
    {
        $this->manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test/manifest.xml');
    }

    public function testFromXml(): void
    {
        $metaData = $this->manifest->getMetadata();
        static::assertEquals('test', $metaData->getName());
        static::assertEquals('shopware AG', $metaData->getAuthor());
        static::assertEquals('(c) by shopware AG', $metaData->getCopyright());
        static::assertEquals('MIT', $metaData->getLicense());
        static::assertEquals('https://test.com/privacy', $metaData->getPrivacy());
        static::assertEquals('1.0.0', $metaData->getVersion());
        static::assertEquals('icon.png', $metaData->getIcon());

        static::assertEquals([
            'en-GB' => 'Swag App Test',
            'de-DE' => 'Swag App Test',
        ], $metaData->getLabel());
        static::assertEquals([
            'en-GB' => 'Test for App System',
            'de-DE' => 'Test für das App System',
        ], $metaData->getDescription());
        static::assertEquals([
            'en-GB' => 'Following personal information will be processed on shopware AG\'s servers:

- Name
- Billing address
- Order value',
            'de-DE' => 'Folgende Nutzerdaten werden auf Servern der shopware AG verarbeitet:

- Name
- Rechnungsadresse
- Bestellwert',
        ], $metaData->getPrivacyPolicyExtensions());
    }

    public function testFromXmlWithoutDescription(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/manifestWithoutDescription.xml');

        $metaData = $manifest->getMetadata();

        static::assertEquals([
            'en-GB' => 'Swag App Test',
            'de-DE' => 'Swag App Test',
        ], $metaData->getLabel());
        static::assertEquals([], $metaData->getDescription());

        $array = $metaData->toArray('en-GB');
        static::assertEquals([], $array['description']);
    }

    public function testValidateTranslationsReturnsMissingTranslationErrorIfTranslationIsMissing(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/invalid-translations-manifest.xml');
        $error = $manifest->getMetadata()->validateTranslations();

        static::assertInstanceOf(MissingTranslationError::class, $error);
        static::assertEquals('Missing translations for "Metadata":
- label: de-DE, fr-FR', $error->getMessage());
    }

    public function testValidateTranslationsReturnsNull(): void
    {
        static::assertNull($this->manifest->getMetadata()->validateTranslations());
    }

    public function testSelfManagedFalseByDefault(): void
    {
        static::assertFalse($this->manifest->getMetadata()->isSelfManaged());
    }

    public function testSetSelfManaged(): void
    {
        $this->manifest->getMetadata()->setSelfManaged(true);

        static::assertTrue($this->manifest->getMetadata()->isSelfManaged());
    }

    public function testSetVersion(): void
    {
        static::assertSame('1.0.0', $this->manifest->getMetadata()->getVersion());

        $this->manifest->getMetadata()->setVersion('2.0.0');

        static::assertSame('2.0.0', $this->manifest->getMetadata()->getVersion());
    }
}
