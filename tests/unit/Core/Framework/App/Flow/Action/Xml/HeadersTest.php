<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Headers;
use Shopware\Core\Framework\App\Flow\Action\Xml\Parameter;

/**
 * @internal
 */
#[CoversClass(Headers::class)]
class HeadersTest extends TestCase
{
    public function testFromXml(): void
    {
        $headers = Headers::fromXml(self::loadElement(<<<'XML'
<headers>
    <parameter type="string" name="content-type" value="application/json"/>
    <parameter type="string" name="auth-token" value="secret"/>
</headers>
XML));

        static::assertCount(2, $headers->getParameters());
        static::assertContainsOnlyInstancesOf(Parameter::class, $headers->getParameters());
        static::assertSame('content-type', $headers->getParameters()[0]->getName());
        static::assertSame('auth-token', $headers->getParameters()[1]->getName());
    }

    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}
