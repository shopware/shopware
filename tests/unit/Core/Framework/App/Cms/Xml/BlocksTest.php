<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Cms\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Cms\Xml\Block;
use Shopware\Core\Framework\App\Cms\Xml\Blocks;

/**
 * @internal
 */
#[CoversClass(Blocks::class)]
class BlocksTest extends TestCase
{
    public function testFromXml(): void
    {
        $blocks = Blocks::fromXml(self::loadElement(<<<'XML'
<blocks>
    <block>
        <name>first-block</name>
        <category>text</category>
    </block>
    <block>
        <name>second-block</name>
        <category>image</category>
    </block>
</blocks>
XML));

        static::assertCount(2, $blocks->getBlocks());
        static::assertContainsOnlyInstancesOf(Block::class, $blocks->getBlocks());
        static::assertSame('first-block', $blocks->getBlocks()[0]->getName());
        static::assertSame('second-block', $blocks->getBlocks()[1]->getName());
    }

    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}
