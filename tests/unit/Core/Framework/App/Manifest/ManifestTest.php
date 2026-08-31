<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\CheckoutGateway;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\ContextGateway;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethods;
use Shopware\Core\Framework\App\Manifest\Xml\Tax\Tax;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Manifest::class)]
class ManifestTest extends TestCase
{
    public function testCreateFromXmlFile(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertSame(__DIR__ . '/_fixtures/test', $manifest->getPath());
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('capabilityPrivilegesProvider')]
    public function testCapabilityPrivilegesAreAddedToPermissions(string $fixture, array $expected): void
    {
        $manifest = Manifest::createFromXmlFile($fixture);

        $privileges = $manifest->getPermissions()?->asParsedPrivileges() ?? [];

        foreach ($expected as $privilege) {
            static::assertContains($privilege, $privileges);
        }
    }

    /**
     * @return iterable<string, array{0: string, 1: list<string>}>
     */
    public static function capabilityPrivilegesProvider(): iterable
    {
        // declares <tax> (and <payments>, which no longer implies a permission), but no gateways
        yield 'tax provider' => [
            __DIR__ . '/_fixtures/test/manifest.xml',
            [Tax::PERMISSION],
        ];

        // declares checkout and context gateways (and no <permissions> block), but no tax
        yield 'checkout and context gateways' => [
            __DIR__ . '/Xml/Gateways/_fixtures/testGateway/manifest.xml',
            [CheckoutGateway::PERMISSION, ContextGateway::PERMISSION],
        ];
    }

    public function testCreateFromXml(): void
    {
        $manifest = Manifest::createFromXml((string) file_get_contents(__DIR__ . '/_fixtures/test/manifest.xml'));

        static::assertSame('test', $manifest->getMetadata()->getName());
    }

    public function testSetPath(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        $manifest->setPath('test');
        static::assertSame('test', $manifest->getPath());
    }

    public function testCreateFromXmlFileThrowsXmlParsingExceptionIfInvalidWebhookEventNames(): void
    {
        $xmlFile = __DIR__ . '/_fixtures/invalid-webhook-event-names-manifest.xml';

        $this->expectExceptionObject(AppException::xmlParsingException($xmlFile, ''));

        Manifest::createFromXmlFile($xmlFile);
    }

    public function testCreateFromXmlThrowsXmlParsingExceptionIfInvalidWebhookEventNames(): void
    {
        $this->expectExceptionObject(AppXmlParsingException::cannotParseContent(''));

        Manifest::createFromXml((string) file_get_contents(__DIR__ . '/_fixtures/invalid-webhook-event-names-manifest.xml'));
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

        static::assertSame([
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

        static::assertSame('>=6.4.0', $manifest->getMetadata()->getCompatibility()->getPrettyString());
    }

    public function testFilledConstraint(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/compatibility/manifest.xml');

        static::assertSame('~6.5.0', $manifest->getMetadata()->getCompatibility()->getPrettyString());
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

        $this->expectExceptionObject(AppException::xmlParsingException('/_fixtures/invalidShippingMethods-manifest.xml', 'name must not be empty'));

        Manifest::validate($fileContent, $file);
    }

    public function testSourceType(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');
        $manifest->setSourceType('test');

        static::assertSame('test', $manifest->getSourceType());
    }

    public function testSourceConfig(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');
        $manifest->setSourceConfig(['test' => 'test']);

        static::assertSame(['test' => 'test'], $manifest->getSourceConfig());
    }

    public function testDuplicateCustomFieldSetNamesAreNotAllowed(): void
    {
        $file = __DIR__ . '/_fixtures/duplicate-custom-field-set-name.xml';
        $fileContent = file_get_contents($file);
        static::assertIsString($fileContent);

        $this->expectException(AppException::class);
        $this->expectExceptionMessageMatches("/Element \'custom-field-set\'\: Duplicate key-sequence \[\'duplicated_custom_field_set\'\] in unique identity-constraint \'uniqueCustomFieldSetName\'/");

        Manifest::validate($fileContent, $file);
    }

    public function testDoesNotValidatePermissionsBackwardsCompatible(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/test/manifest.xml');

        static::assertFalse($manifest->validatesPermissions());
    }

    public function testValidatesPermissions(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/manifestValidatesPermissions.xml');

        static::assertTrue($manifest->validatesPermissions());
    }

    public function testDoesNotValidatePermissions(): void
    {
        $manifestXml = str_replace('validates-permissions="true"', 'validates-permissions="false"', (string) file_get_contents(__DIR__ . '/_fixtures/manifestValidatesPermissions.xml'));
        $manifest = Manifest::createFromXml($manifestXml);

        static::assertFalse($manifest->validatesPermissions());
    }
}
