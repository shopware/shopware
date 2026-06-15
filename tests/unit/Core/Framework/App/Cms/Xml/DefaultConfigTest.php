<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Cms\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\Xml\DefaultConfig;

/**
 * @internal
 */
#[CoversClass(DefaultConfig::class)]
class DefaultConfigTest extends TestCase
{
    public function testFromXml(): void
    {
        $defaultConfig = DefaultConfig::fromXml(self::loadElement(<<<'XML'
<default-config>
    <margin-bottom>5px</margin-bottom>
    <margin-top>10px</margin-top>
    <margin-left>15px</margin-left>
    <margin-right>20px</margin-right>
    <sizing-mode>boxed</sizing-mode>
    <background-color>#000</background-color>
</default-config>
XML));

        static::assertSame(
            [
                'marginTop' => '10px',
                'marginRight' => '20px',
                'marginBottom' => '5px',
                'marginLeft' => '15px',
                'sizingMode' => 'boxed',
                'backgroundColor' => '#000',
            ],
            $defaultConfig->toArray('en-GB')
        );
        static::assertSame('5px', $defaultConfig->getMarginBottom());
        static::assertSame('10px', $defaultConfig->getMarginTop());
        static::assertSame('15px', $defaultConfig->getMarginLeft());
        static::assertSame('20px', $defaultConfig->getMarginRight());
        static::assertSame('boxed', $defaultConfig->getSizingMode());
        static::assertSame('#000', $defaultConfig->getBackgroundColor());
    }

    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}
