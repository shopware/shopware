<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;

/**
 * @package content
 *
 * @internal
 *
 * @covers \Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement
 */
class ConfigXmlElementTest extends TestCase
{
    public function testJsonSerialize(): void
    {
        $extendedConfigXmlElement = new class() extends ConfigXmlElement {
            public $extensions = [];

            public string $testData = 'TEST_DATA';

            public static function fromXml(\DOMElement $element): ConfigXmlElement
            {
                return new self();
            }
        };

        $serializeResult = $extendedConfigXmlElement->jsonSerialize();
        static::assertEquals(['testData' => 'TEST_DATA'], $serializeResult);

        static::assertEquals([], $extendedConfigXmlElement->extensions);
        static::assertEquals('TEST_DATA', $extendedConfigXmlElement->testData);
    }
}
