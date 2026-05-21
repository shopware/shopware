<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Cms\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\Xml\Config;

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
    <config-value name="display-mode" source="static" value="cover"/>
    <config-value name="min-height" source="static" value="300px"/>
</config>
XML));

        static::assertSame(
            [
                'displayMode' => [
                    'source' => 'static',
                    'value' => 'cover',
                ],
                'minHeight' => [
                    'source' => 'static',
                    'value' => '300px',
                ],
            ],
            $config->toArray('en-GB')
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
