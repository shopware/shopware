<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Parameter;
use Shopware\Core\Framework\App\Flow\Action\Xml\Parameters;

/**
 * @internal
 */
#[CoversClass(Parameters::class)]
class ParametersTest extends TestCase
{
    public function testFromXml(): void
    {
        $parameters = Parameters::fromXml(self::loadElement(<<<'XML'
<parameters>
    <parameter type="string" name="to" value="{{ customer.email }}"/>
    <parameter type="string" name="subject" value="Order placed"/>
    <parameter type="int" name="orderNumber" value="{{ order.orderNumber }}"/>
</parameters>
XML));

        static::assertCount(3, $parameters->getParameters());
        static::assertContainsOnlyInstancesOf(Parameter::class, $parameters->getParameters());
        static::assertSame('to', $parameters->getParameters()[0]->getName());
        static::assertSame('subject', $parameters->getParameters()[1]->getName());
        static::assertSame('orderNumber', $parameters->getParameters()[2]->getName());
    }

    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}
