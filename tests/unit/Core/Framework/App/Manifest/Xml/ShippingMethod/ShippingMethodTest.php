<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\ShippingMethod;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\App\Exception\InvalidArgumentException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethod;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethods;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[CoversClass(ShippingMethod::class)]
class ShippingMethodTest extends TestCase
{
    public const XSD_FILE = __DIR__ . '/../../../../../../../../src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd';
    public const TEST_MANIFEST = __DIR__ . '/../../_fixtures/test/manifest.xml';
    public const INVALID_TEST_MANIFEST = __DIR__ . '/../../_fixtures/invalidShippingMethods-manifest.xml';

    public function testFromXml(): void
    {
        $shipment = Manifest::createFromXmlFile(self::TEST_MANIFEST)->getShippingMethods();
        static::assertNotNull($shipment, 'No shipments found in manifest.xml.');

        $shippingMethods = $shipment->getShippingMethods();
        static::assertCount(2, $shippingMethods);

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

        foreach ($shippingMethods as $i => $shippingMethod) {
            static::assertSame($shippingMethod->getIdentifier(), $expectedValues[$i]['identifier']);
            static::assertSame($shippingMethod->getName(), $expectedValues[$i]['name']);
        }
    }

    public function testFromXmlWithDeliveryTime(): void
    {
        $shippingMethodDomElement = XmlUtils::loadFile(self::TEST_MANIFEST, self::XSD_FILE)->getElementsByTagName('shipping-method')->item(0);
        static::assertInstanceOf(\DOMElement::class, $shippingMethodDomElement);

        $deliveryTime = ShippingMethod::fromXml($shippingMethodDomElement)->getDeliveryTime();

        static::assertSame('4b00146bdc8b4175b12d3fc36ec114c8', $deliveryTime->getId());
        static::assertSame('Short delivery time 1-2 days', $deliveryTime->getName());
        static::assertSame(1, $deliveryTime->getMin());
        static::assertSame(2, $deliveryTime->getMax());
        static::assertSame('day', $deliveryTime->getUnit());
    }

    public function testFromXmlShouldThrowExceptionWithoutRequiredFieldName(): void
    {
        $shippingMethodOne = XmlUtils::loadFile(self::INVALID_TEST_MANIFEST, self::XSD_FILE)->getElementsByTagName('shipping-method')->item(0);

        static::assertInstanceOf(\DOMElement::class, $shippingMethodOne);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name must not be empty');
        ShippingMethod::fromXml($shippingMethodOne);
    }

    public function testFromXmlShouldThrowExceptionWithoutRequiredFieldIdentifier(): void
    {
        $shippingMethodTwo = XmlUtils::loadFile(self::INVALID_TEST_MANIFEST, self::XSD_FILE)->getElementsByTagName('shipping-method')->item(1);

        static::assertInstanceOf(\DOMElement::class, $shippingMethodTwo);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('identifier must not be empty');
        ShippingMethod::fromXml($shippingMethodTwo);
    }

    public function testToArray(): void
    {
        $manifestShippingMethod = Manifest::createFromXmlFile(self::TEST_MANIFEST)->getShippingMethods();
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
        $manifestShippingMethod = Manifest::createFromXmlFile(self::TEST_MANIFEST)->getShippingMethods();
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
        $manifestShippingMethod = Manifest::createFromXmlFile(self::TEST_MANIFEST)->getShippingMethods();
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
        $manifestShippingMethod = Manifest::createFromXmlFile(self::TEST_MANIFEST)->getShippingMethods();
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

    public function testGetPosition(): void
    {
        $manifestShippingMethods = Manifest::createFromXmlFile(self::TEST_MANIFEST)->getShippingMethods();
        static::assertInstanceOf(ShippingMethods::class, $manifestShippingMethods);
        $manifestShippingMethods = $manifestShippingMethods->getShippingMethods();
        $shippingMethod = $manifestShippingMethods[0];
        static::assertInstanceOf(ShippingMethod::class, $shippingMethod);

        static::assertSame(3, $shippingMethod->getPosition());
    }

    public function testGetPositionDefaultValue(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/shippingMethod-manifest.xml');

        $manifestShippingMethods = $manifest->getShippingMethods();
        static::assertInstanceOf(ShippingMethods::class, $manifestShippingMethods);
        $manifestShippingMethods = $manifestShippingMethods->getShippingMethods();
        $shippingMethod = $manifestShippingMethods[1];
        static::assertInstanceOf(ShippingMethod::class, $shippingMethod);

        static::assertSame(ShippingMethodEntity::POSITION_DEFAULT, $shippingMethod->getPosition());
    }

    public function testGetTrackingUrl(): void
    {
        $manifestShippingMethods = Manifest::createFromXmlFile(self::TEST_MANIFEST)->getShippingMethods();
        static::assertInstanceOf(ShippingMethods::class, $manifestShippingMethods);
        $manifestShippingMethods = $manifestShippingMethods->getShippingMethods();
        $shippingMethod = $manifestShippingMethods[0];
        static::assertInstanceOf(ShippingMethod::class, $shippingMethod);

        $expectedValues = [
            'en-GB' => 'https://www.mytrackingurl.com',
            'de-DE' => 'https://de.mytrackingurl.com',
        ];

        static::assertSame($expectedValues, $shippingMethod->getTrackingUrl());
    }

    public function testFromXmlShouldContainActivePropertyAsBool(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/shippingMethod-manifest.xml');

        $shippingMethods = $manifest->getShippingMethods()?->getShippingMethods();
        static::assertIsArray($shippingMethods);

        static::assertArrayHasKey(0, $shippingMethods);
        $firstShippingMethod = $shippingMethods[0]->getVars();
        static::assertArrayHasKey('active', $firstShippingMethod);
        static::assertIsBool($firstShippingMethod['active']);
        static::assertTrue($firstShippingMethod['active']);

        static::assertArrayHasKey(1, $shippingMethods);
        $secondShippingMethod = $shippingMethods[1]->getVars();
        static::assertArrayHasKey('active', $secondShippingMethod);
        static::assertIsBool($secondShippingMethod['active']);
        static::assertFalse($secondShippingMethod['active']);
    }
}
