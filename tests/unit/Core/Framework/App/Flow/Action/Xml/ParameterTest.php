<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Parameter;

/**
 * @internal
 */
#[CoversClass(Parameter::class)]
class ParameterTest extends TestCase
{
    public function testFromXml(): void
    {
        $parameter = Parameter::fromXml(self::loadElement(
            '<parameter type="string" name="to" value="{{ customer.email }}"/>'
        ));

        static::assertSame('string', $parameter->getType());
        static::assertSame('to', $parameter->getName());
        static::assertSame('{{ customer.email }}', $parameter->getValue());
        static::assertSame(
            [
                'type' => 'string',
                'name' => 'to',
                'value' => '{{ customer.email }}',
            ],
            $parameter->toArray('en-GB')
        );
    }

    public function testFromXmlKeepsJsonLikeStringValue(): void
    {
        $jsonString = <<<EOD
{
  "street": "{{ order.addresses[0].street }}",
  "additional_one": "{{ order.addresses[0].additionalAddressLine1 }}",
  "additional_two": "{{ order.addresses[0].additionalAddressLine2 }}",
  "city": "{{ order.addresses[0].city }}",
  "zipcode": "{{ order.addresses[0].zipcode }}"
}
EOD;
        $document = new \DOMDocument();
        $parameter = $document->createElement('parameter');
        $parameter->setAttribute('type', 'string');
        $parameter->setAttribute('name', 'payload');
        $parameter->setAttribute('value', $jsonString);

        $result = Parameter::fromXml($parameter);

        static::assertSame($jsonString, $result->getValue());
        static::assertSame(
            [
                'type' => 'string',
                'name' => 'payload',
                'value' => $jsonString,
            ],
            $result->toArray('en-GB')
        );
    }

    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}
