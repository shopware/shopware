<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action\Xml;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Flow\Action\Xml\Metadata;

/**
 * @internal
 */
#[CoversClass(Metadata::class)]
class MetadataTest extends TestCase
{
    public function testFromXml(): void
    {
        $metadata = Metadata::fromXml(self::loadElement(<<<'XML'
<meta>
    <name>mail.send</name>
    <badge>app</badge>
    <label>Send mail</label>
    <label lang="de-DE">Mail senden</label>
    <headline>Mail</headline>
    <headline lang="de-DE">Mail DE</headline>
    <description>Send mail to customer</description>
    <description lang="de-DE">Mail an Kunden senden</description>
    <url>https://example.com/flow-action</url>
    <icon>resource/mail</icon>
    <sw-icon>sw-mail</sw-icon>
    <requirements>order</requirements>
    <requirements>customer</requirements>
    <delayable>true</delayable>
</meta>
XML));

        static::assertSame(
            [
                'label' => [
                    'en-GB' => 'Send mail',
                    'de-DE' => 'Mail senden',
                ],
                'description' => [
                    'en-GB' => 'Send mail to customer',
                    'de-DE' => 'Mail an Kunden senden',
                ],
                'name' => 'mail.send',
                'url' => 'https://example.com/flow-action',
                'requirements' => ['order', 'customer'],
                'icon' => 'resource/mail',
                'swIcon' => 'sw-mail',
                'headline' => [
                    'en-GB' => 'Mail',
                    'de-DE' => 'Mail DE',
                ],
                'delayable' => true,
                'badge' => 'app',
            ],
            $metadata->toArray('en-GB')
        );
        static::assertSame('mail.send', $metadata->getName());
        static::assertSame('https://example.com/flow-action', $metadata->getUrl());
        static::assertSame(['order', 'customer'], $metadata->getRequirements());
        static::assertSame('resource/mail', $metadata->getIcon());
        static::assertSame('sw-mail', $metadata->getSwIcon());
        static::assertTrue($metadata->getDelayable());
        static::assertSame('app', $metadata->getBadge());
    }

    private static function loadElement(string $xml): \DOMElement
    {
        $document = new \DOMDocument();
        static::assertTrue($document->loadXML($xml));
        static::assertInstanceOf(\DOMElement::class, $document->documentElement);

        return $document->documentElement;
    }
}
