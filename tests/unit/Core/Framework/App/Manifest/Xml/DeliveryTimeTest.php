<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\DeliveryTime;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[CoversClass(DeliveryTime::class)]
class DeliveryTimeTest extends TestCase
{
    public const XSD_FILE = __DIR__ . '/../../../../../../../src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd';
    public const VALID_MANIFEST = __DIR__ . '/_fixtures/DeliveryTime/valid_manifest.xml';

    public function testFromXml(): void
    {
        $xmlDocument = XmlUtils::loadFile(self::VALID_MANIFEST, self::XSD_FILE);

        $deliveryTimeOneDomElement = $xmlDocument->getElementsByTagName('delivery-time')->item(0);
        static::assertInstanceOf(\DOMElement::class, $deliveryTimeOneDomElement);

        $deliveryTimeOne = DeliveryTime::fromXml($deliveryTimeOneDomElement);

        static::assertSame('4b00146bdc8b4175b12d3fc36ec114c8', $deliveryTimeOne->getId());
        static::assertSame(1, $deliveryTimeOne->getMin());
        static::assertSame(2, $deliveryTimeOne->getMax());
        static::assertSame('day', $deliveryTimeOne->getUnit());
        static::assertSame('Short delivery time 1-2 days', $deliveryTimeOne->getName());

        $deliveryTimeTwoDomElement = $xmlDocument->getElementsByTagName('delivery-time')->item(1);
        static::assertInstanceOf(\DOMElement::class, $deliveryTimeTwoDomElement);

        $deliveryTimeTwo = DeliveryTime::fromXml($deliveryTimeTwoDomElement);

        static::assertSame('c8864e36a4d84bd4a16cc31b5953431b', $deliveryTimeTwo->getId());
        static::assertSame(2, $deliveryTimeTwo->getMin());
        static::assertSame(4, $deliveryTimeTwo->getMax());
        static::assertSame('day', $deliveryTimeTwo->getUnit());
        static::assertSame('Long delivery time 2-4 days', $deliveryTimeTwo->getName());
    }
}
