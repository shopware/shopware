<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Tax;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\TaxProvider;

/**
 * @internal
 */
#[CoversClass(TaxProvider::class)]
class TaxProviderTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getTax());
        $taxProviders = $manifest->getTax()->getTaxProviders();
        static::assertCount(2, $taxProviders);

        $firstProvider = $taxProviders[0];
        static::assertSame('myTaxProvider', $firstProvider->getIdentifier());
        static::assertSame('My tax provider', $firstProvider->getName());
        static::assertSame('https://tax-provider.app/process', $firstProvider->getProcessUrl());

        $secondProvider = $taxProviders[1];
        static::assertSame('mySecondTaxProvider', $secondProvider->getIdentifier());
        static::assertSame('My second tax provider', $secondProvider->getName());
        static::assertSame('https://tax-provider-2.app/process', $secondProvider->getProcessUrl());
    }

    public function testItThrowsOnMissingIdentifier(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches('/identifier must not be empty/');

        Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/invalidTaxProvider/manifest-missing-identifier.xml');
    }

    public function testItThrowsOnEmptyName(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches('/name must not be empty/');

        Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/invalidTaxProvider/manifest-missing-name.xml');
    }

    public function testItThrowsOnEmptyPriority(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches('/priority must not be empty/');

        Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/invalidTaxProvider/manifest-missing-priority.xml');
    }

    public function testItThrowsOnEmptyProcessUrl(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches('/processUrl must not be empty/');

        Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/invalidTaxProvider/manifest-missing-process-url.xml');
    }
}
