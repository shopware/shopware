<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Action;
use Shopware\Core\Framework\App\Flow\Action\Xml\Actions;

/**
 * @internal
 */
#[CoversClass(Actions::class)]
class ActionsTest extends TestCase
{
    public function testFromXml(): void
    {
        $actions = Actions::fromXml(self::loadElement(<<<'XML'
<flow-actions>
    <flow-action>
        <meta>
            <name>first.action</name>
            <label>First action</label>
            <url>https://example.com/first</url>
        </meta>
        <headers/>
        <parameters/>
        <config/>
    </flow-action>
    <flow-action>
        <meta>
            <name>second.action</name>
            <label>Second action</label>
            <url>https://example.com/second</url>
        </meta>
        <headers/>
        <parameters/>
        <config/>
    </flow-action>
</flow-actions>
XML));

        static::assertCount(2, $actions->getActions());
        static::assertContainsOnlyInstancesOf(Action::class, $actions->getActions());
        static::assertSame('first.action', $actions->getActions()[0]->getMeta()->getName());
        static::assertSame('second.action', $actions->getActions()[1]->getMeta()->getName());
    }

    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}
