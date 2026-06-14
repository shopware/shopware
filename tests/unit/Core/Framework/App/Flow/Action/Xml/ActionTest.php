<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Action;

/**
 * @internal
 */
#[CoversClass(Action::class)]
class ActionTest extends TestCase
{
    public function testFromXml(): void
    {
        $action = self::createAction();

        static::assertSame('mail.send', $action->getMeta()->getName());
        static::assertCount(1, $action->getHeaders()->getParameters());
        static::assertCount(2, $action->getParameters()->getParameters());
        static::assertCount(1, $action->getConfig()->getConfig());
    }

    public function testToArray(): void
    {
        $result = self::createAction()->toArray('en-GB');

        static::assertSame('mail.send', $result['name']);
        static::assertSame('https://example.com/flow-action', $result['url']);
        static::assertTrue($result['delayable']);
        static::assertSame(['order'], $result['requirements']);
        static::assertSame(['en-GB' => 'Send mail'], $result['label']);
        static::assertSame(['en-GB' => 'Send mail to customer'], $result['description']);
        static::assertSame(['en-GB' => 'Mail'], $result['headline']);
        static::assertSame('sw-mail', $result['swIcon']);
        static::assertCount(2, $result['parameters']);
        static::assertSame('string', $result['parameters'][0]['type']);
        static::assertSame('to', $result['parameters'][0]['name']);
        static::assertSame('{{ customer.email }}', $result['parameters'][0]['value']);
        static::assertSame('string', $result['parameters'][1]['type']);
        static::assertSame('subject', $result['parameters'][1]['name']);
        static::assertSame('Order placed', $result['parameters'][1]['value']);
        static::assertCount(1, $result['headers']);
        static::assertSame('string', $result['headers'][0]['type']);
        static::assertSame('content-type', $result['headers'][0]['name']);
        static::assertSame('application/json', $result['headers'][0]['value']);
        static::assertSame('recipient', $result['config'][0]['name']);
        static::assertSame('text', $result['config'][0]['type']);
    }

    private static function createAction(): Action
    {
        return Action::fromXml(self::loadElement(<<<'XML'
<flow-action>
    <meta>
        <name>mail.send</name>
        <label>Send mail</label>
        <description>Send mail to customer</description>
        <headline>Mail</headline>
        <url>https://example.com/flow-action</url>
        <sw-icon>sw-mail</sw-icon>
        <requirements>order</requirements>
        <delayable>true</delayable>
    </meta>
    <headers>
        <parameter type="string" name="content-type" value="application/json"/>
    </headers>
    <parameters>
        <parameter type="string" name="to" value="{{ customer.email }}"/>
        <parameter type="string" name="subject" value="Order placed"/>
    </parameters>
    <config>
        <input-field>
            <name>recipient</name>
            <label>Recipient</label>
            <required>true</required>
        </input-field>
    </config>
</flow-action>
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
