<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;

class MetadataTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test/manifest.xml');

        $metaData = $manifest->getMetadata();
        static::assertEquals('SwagApp', $metaData->getName());
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
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifestWithoutDescription.xml');

        $metaData = $manifest->getMetadata();

        static::assertEquals([
            'en-GB' => 'Swag App Test',
            'de-DE' => 'Swag App Test',
        ], $metaData->getLabel());
        static::assertEquals([], $metaData->getDescription());

        $array = $metaData->toArray('en-GB');
        static::assertEquals([], $array['description']);
    }
}
