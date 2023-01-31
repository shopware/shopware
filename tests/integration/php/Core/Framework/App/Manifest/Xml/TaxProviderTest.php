<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Manifest\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;

/**
 * @package checkout
 *
 * @internal
 */
class TaxProviderTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getTax());
        static::assertCount(2, $manifest->getTax()->getTaxProviders());

        $firstProvider = $manifest->getTax()->getTaxProviders()[0];
        static::assertNotNull($firstProvider);
        static::assertSame('myTaxProvider', $firstProvider->getIdentifier());
        static::assertSame('My tax provider', $firstProvider->getName());
        static::assertSame('https://tax-provider.app/process', $firstProvider->getProcessUrl());

        $secondProvider = $manifest->getTax()->getTaxProviders()[1];
        static::assertNotNull($secondProvider);
        static::assertSame('mySecondTaxProvider', $secondProvider->getIdentifier());
        static::assertSame('My second tax provider', $secondProvider->getName());
        static::assertSame('https://tax-provider-2.app/process', $secondProvider->getProcessUrl());
    }

    public function testItThrowsOnMissingIdentifier(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessageMatches('/identifier must not be empty/');
        Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/invalidTaxProvider/manifest-missing-identifier.xml');
    }

    public function testItThrowsOnEmptyName(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessageMatches('/name must not be empty/');
        Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/invalidTaxProvider/manifest-missing-name.xml');
    }

    public function testItThrowsOnEmptyPriority(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessageMatches('/priority must not be empty/');
        Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/invalidTaxProvider/manifest-missing-priority.xml');
    }

    public function testItThrowsOnEmptyProcessUrl(): void
    {
        static::expectException(XmlParsingException::class);
        static::expectExceptionMessageMatches('/processUrl must not be empty/');
        Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/invalidTaxProvider/manifest-missing-process-url.xml');
    }
}
