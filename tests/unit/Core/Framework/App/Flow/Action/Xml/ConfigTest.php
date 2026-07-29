<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Config;
use Shopware\Core\Framework\App\Flow\Action\Xml\InputField;

/**
 * @internal
 */
#[CoversClass(Config::class)]
class ConfigTest extends TestCase
{
    public function testFromXml(): void
    {
        $config = Config::fromXml(self::loadElement(<<<'XML'
<config>
    <input-field>
        <name>subject</name>
        <label>Subject</label>
    </input-field>
    <input-field type="single-select">
        <name>method</name>
        <options>
            <option value="smtp">
                <label>SMTP</label>
            </option>
        </options>
    </input-field>
</config>
XML));

        static::assertCount(2, $config->getConfig());
        static::assertContainsOnlyInstancesOf(InputField::class, $config->getConfig());
        static::assertSame('subject', $config->getConfig()[0]->getName());
        static::assertSame('text', $config->getConfig()[0]->getType());
        static::assertSame('method', $config->getConfig()[1]->getName());
        static::assertSame('single-select', $config->getConfig()[1]->getType());
    }

    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}
