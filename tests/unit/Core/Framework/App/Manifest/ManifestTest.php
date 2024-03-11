<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethods;

/**
 * @internal
 */
#[CoversClass(Manifest::class)]
class ManifestTest extends TestCase
{
    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertEquals(__DIR__ . '/_fixtures/test', $manifest->getPath());
    }

    public function testSetPath(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        $manifest->setPath('test');
        static::assertEquals('test', $manifest->getPath());
    }

    public function testThrowsXmlParsingExceptionIfInvalidWebhookEventNames(): void
    {
        $this->expectException(AppException::class);
        $this->expectExceptionMessage('attribute \'event\': \'test event\' is not a valid value');
        $this->expectExceptionMessage('attribute \'event\': \'\' is not a valid value');
        $this->expectExceptionMessage('Duplicate key-sequence [\'hook2\'] in unique identity-constraint \'uniqueWebhookName\'');

        Manifest::createFromXmlFile(__DIR__ . '/_fixtures/invalid-webhook-event-names-manifest.xml');
    }

    public function testXSChoice(): void
    {
        $fixedOrderManifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/fixed-order-manifest.xml');
        $randomOrderManifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/random-order-manifest.xml');

        static::assertEquals($fixedOrderManifest->getMetadata(), $randomOrderManifest->getMetadata());
    }

    public function testGetAllHosts(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertEquals([
            'my.app.com',
            'test.com',
            'base-url.com',
            'main-module',
            'swag-test.com',
            'payment.app',
            'tax-provider.app',
            'tax-provider-2.app',
        ], $manifest->getAllHosts());
    }

    public function testGetEmptyConstraint(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertEquals('>=6.4.0', $manifest->getMetadata()->getCompatibility()->getPrettyString());
    }

    public function testFilledConstraint(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/compatibility/manifest.xml');

        static::assertEquals('~6.5.0', $manifest->getMetadata()->getCompatibility()->getPrettyString());
    }

    public function testGetShippingMethods(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertInstanceOf(ShippingMethods::class, $manifest->getShippingMethods());
    }

    public function testGetShippingMethodsManifestWithoutShoppingMethodsShouldBeNull(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test-manifest-withoutShippingMethods.xml');

        static::assertNull($manifest->getShippingMethods());
    }

    public function testValidate(): void
    {
        $file = __DIR__ . '/_fixtures/shippingMethod-manifest.xml';
        $fileContent = file_get_contents($file);
        static::assertIsString($fileContent);

        Manifest::validate($fileContent, $file);
    }

    public function testValidateWithInvalidShippingMethod(): void
    {
        $file = '/_fixtures/invalidShippingMethods-manifest.xml';
        $fileContent = file_get_contents(__DIR__ . $file);
        static::assertIsString($fileContent);

        $this->expectException(AppException::class);
        $this->expectExceptionMessage('Unable to parse file "/_fixtures/invalidShippingMethods-manifest.xml". Message: name must not be empty');

        Manifest::validate($fileContent, $file);
    }
}
