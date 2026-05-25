<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Cms\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\Xml\Slot;

/**
 * @internal
 */
#[CoversClass(Slot::class)]
class SlotTest extends TestCase
{
    public function testFromXml(): void
    {
        $slot = self::createSlot();

        static::assertSame('main', $slot->getName());
        static::assertSame('image', $slot->getType());
        static::assertSame(3, $slot->getPosition());
        static::assertSame(
            [
                'displayMode' => [
                    'source' => 'static',
                    'value' => 'cover',
                ],
            ],
            $slot->getConfig()->toArray('en-GB')
        );
    }

    public function testToArray(): void
    {
        static::assertSame(
            [
                'name' => 'main',
                'type' => 'image',
                'position' => 3,
                'config' => [
                    'displayMode' => [
                        'source' => 'static',
                        'value' => 'cover',
                    ],
                ],
            ],
            self::createSlot()->toArray('en-GB')
        );
    }

    private static function createSlot(): Slot
    {
        return Slot::fromXml(self::loadElement(<<<'XML'
<slot name="main" type="image" position="3">
    <config>
        <config-value name="display-mode" source="static" value="cover"/>
    </config>
</slot>
XML));
    }

    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}
