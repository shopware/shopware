<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethods;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod
 */
class ShippingMethodTest extends TestCase
{
    public const XSD_FILE = __DIR__ . '/../../../../../../../src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd';
    public const TEST_MANIFEST = __DIR__ . '/../_fixtures/test-manifest.xml';
    public const INVALID_TEST_MANIFEST = __DIR__ . '/../_fixtures/invalidShippingMethods-manifest.xml';

    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(self::TEST_MANIFEST);

        $shipment = $manifest->getShippingMethods();
        static::assertNotNull($shipment, 'No shipments found in manifest.xml.');

        $shippingMethods = $shipment->getShippingMethods();
        static::assertCount(2, $shippingMethods);

        $this->checkShippingMethodValues($shippingMethods);
    }

    public function testFromXmlShouldThrowExceptionWithoutRequiredFields(): void
    {
        $xmlDocument = XmlUtils::loadFile(self::INVALID_TEST_MANIFEST, self::XSD_FILE);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name must not be empty');
        $shippingMethodOne = $xmlDocument->getElementsByTagName('shipping-method')->item(0);

        static::assertInstanceOf(\DOMElement::class, $shippingMethodOne);
        ShippingMethod::fromXml($shippingMethodOne);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('identifier must not be empty');
        $shippingMethodTwo = $xmlDocument->getElementsByTagName('shipping-method')->item(1);

        static::assertInstanceOf(\DOMElement::class, $shippingMethodTwo);
        ShippingMethod::fromXml($shippingMethodTwo);
    }

    public function testToArray(): void
    {
        $manifest = Manifest::createFromXmlFile(self::TEST_MANIFEST);

        $manifestShippingMethod = $manifest->getShippingMethods();
        static::assertInstanceOf(ShippingMethods::class, $manifestShippingMethod);

        $result = $manifestShippingMethod->toArray('en-GB');
        static::assertArrayHasKey('shippingMethods', $result);

        $shippingMethods = $result['shippingMethods'];
        static::assertCount(2, $shippingMethods);
        static::assertArrayHasKey(0, $shippingMethods);

        $shippingMethod = $shippingMethods[0];
        static::assertInstanceOf(ShippingMethod::class, $shippingMethod);
        static::assertSame('swagFirstShippingMethod', $shippingMethod->getIdentifier());

        $result = $shippingMethod->toArray('en-GB');

        static::assertArrayHasKey('name', $result);
        $name = $result['name'];
        static::assertIsArray($name);
        static::assertArrayHasKey('en-GB', $name);
        static::assertArrayHasKey('de-DE', $name);
        static::assertSame('First shipping method', $name['en-GB']);
        static::assertSame('Erste Versandmethode', $name['de-DE']);

        static::assertArrayHasKey('description', $result);
        $description = $result['description'];
        static::assertIsArray($description);
        static::assertArrayHasKey('en-GB', $description);
        static::assertArrayHasKey('de-DE', $description);
        static::assertSame('This is a simple description', $description['en-GB']);
        static::assertSame('Das ist eine einfache Beschreibung', $description['de-DE']);

        static::assertArrayHasKey('appShippingMethod', $result);
        $appShippingMethod = $result['appShippingMethod'];
        static::assertIsArray($appShippingMethod);
        static::assertArrayHasKey('identifier', $appShippingMethod);
        static::assertSame('swagFirstShippingMethod', $appShippingMethod['identifier']);

        static::assertArrayHasKey('icon', $result);
        static::assertSame('icon.png', $result['icon']);
    }

    public function testGetDescription(): void
    {
        $manifest = Manifest::createFromXmlFile(self::TEST_MANIFEST);

        $manifestShippingMethod = $manifest->getShippingMethods();
        static::assertInstanceOf(ShippingMethods::class, $manifestShippingMethod);

        $result = $manifestShippingMethod->toArray('en-GB');
        static::assertArrayHasKey('shippingMethods', $result);

        $shippingMethods = $result['shippingMethods'];
        static::assertCount(2, $shippingMethods);
        static::assertArrayHasKey(0, $shippingMethods);

        $shippingMethod = $shippingMethods[0];
        static::assertInstanceOf(ShippingMethod::class, $shippingMethod);

        $descriptions = $shippingMethod->getDescription();
        static::assertArrayHasKey('en-GB', $descriptions);
        static::assertArrayHasKey('de-DE', $descriptions);
        static::assertSame('This is a simple description', $descriptions['en-GB']);
        static::assertSame('Das ist eine einfache Beschreibung', $descriptions['de-DE']);
    }

    public function testGetName(): void
    {
        $manifest = Manifest::createFromXmlFile(self::TEST_MANIFEST);

        $manifestShippingMethod = $manifest->getShippingMethods();
        static::assertInstanceOf(ShippingMethods::class, $manifestShippingMethod);

        $result = $manifestShippingMethod->toArray('en-GB');
        static::assertArrayHasKey('shippingMethods', $result);

        $shippingMethods = $result['shippingMethods'];
        static::assertCount(2, $shippingMethods);
        static::assertArrayHasKey(0, $shippingMethods);

        $shippingMethod = $shippingMethods[0];
        static::assertInstanceOf(ShippingMethod::class, $shippingMethod);

        $names = $shippingMethod->getName();
        static::assertArrayHasKey('en-GB', $names);
        static::assertArrayHasKey('de-DE', $names);
        static::assertSame('First shipping method', $names['en-GB']);
        static::assertSame('Erste Versandmethode', $names['de-DE']);
    }

    public function testGetIcon(): void
    {
        $manifest = Manifest::createFromXmlFile(self::TEST_MANIFEST);

        $manifestShippingMethod = $manifest->getShippingMethods();
        static::assertInstanceOf(ShippingMethods::class, $manifestShippingMethod);

        $result = $manifestShippingMethod->toArray('en-GB');
        static::assertArrayHasKey('shippingMethods', $result);

        $shippingMethods = $result['shippingMethods'];
        static::assertCount(2, $shippingMethods);
        static::assertArrayHasKey(0, $shippingMethods);

        $shippingMethod = $shippingMethods[0];
        static::assertInstanceOf(ShippingMethod::class, $shippingMethod);

        static::assertSame('icon.png', $shippingMethod->getIcon());
    }

    /**
     * @param ShippingMethod[] $shippingMethods
     */
    private function checkShippingMethodValues(array $shippingMethods): void
    {
        $expectedValues = [
            [
                'identifier' => 'swagFirstShippingMethod',
                'name' => [
                    'en-GB' => 'First shipping method',
                    'de-DE' => 'Erste Versandmethode',
                ],
            ],
            [
                'identifier' => 'swagSecondShippingMethod',
                'name' => [
                    'en-GB' => 'second Shipping Method',
                ],
            ],
        ];

        for ($i = 0, $iMax = \count($shippingMethods); $i < $iMax; ++$i) {
            static::assertInstanceOf(ShippingMethod::class, $shippingMethods[$i]);
            static::assertSame($shippingMethods[$i]->getIdentifier(), $expectedValues[$i]['identifier']);
            static::assertSame($shippingMethods[$i]->getName(), $expectedValues[$i]['name']);
        }
    }
}
