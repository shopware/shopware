<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\App\Manifest\Xml\ShippingMethod
 */
class ShippingMethodTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test-manifest.xml');

        $shipment = $manifest->getShipments();
        static::assertNotNull($shipment, 'No shipments found in manifest.xml.');

        $shippingMethods = $shipment->getShippingMethods();
        static::assertCount(2, $shippingMethods);

        $this->checkShippingMethodValues($shippingMethods);
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
                    'en-GB' => 'first Shipping Method',
                    'de-DE' => 'erste Versandmethode',
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
