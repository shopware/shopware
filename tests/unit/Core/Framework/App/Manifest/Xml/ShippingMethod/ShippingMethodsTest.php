<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\ShippingMethod;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethod;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod\ShippingMethods;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
#[CoversClass(ShippingMethod::class)]
class ShippingMethodsTest extends TestCase
{
    public const XSD_FILE = __DIR__ . '/../../../../../../../../src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd';
    public const TEST_MANIFEST = __DIR__ . '/../../_fixtures/test/manifest.xml';

    public function testGetShippingMethods(): void
    {
        $manifestShippingMethod = Manifest::createFromXmlFile(self::TEST_MANIFEST)->getShippingMethods();
        static::assertInstanceOf(ShippingMethods::class, $manifestShippingMethod);

        $result = $manifestShippingMethod->getShippingMethods();
        static::assertCount(2, $result);

        static::assertSame('swagFirstShippingMethod', $result[0]->getIdentifier());
        static::assertSame('swagSecondShippingMethod', $result[1]->getIdentifier());
    }

    public function testFromXml(): void
    {
        $xmlShippingMethods = XmlUtils::loadFile(self::TEST_MANIFEST, self::XSD_FILE)->getElementsByTagName('shipping-methods')->item(0);

        static::assertInstanceOf(\DOMElement::class, $xmlShippingMethods);
        $result = ShippingMethods::fromXml($xmlShippingMethods);

        static::assertCount(2, $result->getShippingMethods());
    }
}
