<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Cookie\Cookies;

/**
 * @internal
 */
#[CoversClass(Cookies::class)]
class CookiesTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getCookies());
        static::assertCount(2, $manifest->getCookies()->getCookies());
        static::assertNull($manifest->getCookies()->getDefaultTargetGroup());
    }

    public function testIgnoresEmptyDefaultTargetGroupAttribute(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <cookies default-target-group="">
        <group>
            <snippet-name>test.group</snippet-name>
        </group>
    </cookies>
</manifest>
XML;

        $document = new \DOMDocument();
        $document->loadXML($xml);
        $cookiesElement = $document->getElementsByTagName('cookies')->item(0);
        static::assertInstanceOf(\DOMElement::class, $cookiesElement);

        $cookies = Cookies::fromXml($cookiesElement);

        static::assertNull($cookies->getDefaultTargetGroup());
    }

    public function testIgnoresEmptyTargetGroupAttribute(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <cookies>
        <group target-group="">
            <snippet-name>test.group</snippet-name>
        </group>
    </cookies>
</manifest>
XML;

        $document = new \DOMDocument();
        $document->loadXML($xml);
        $cookiesElement = $document->getElementsByTagName('cookies')->item(0);
        static::assertInstanceOf(\DOMElement::class, $cookiesElement);

        $cookies = Cookies::fromXml($cookiesElement);

        $cookieGroups = $cookies->getCookies();
        static::assertCount(1, $cookieGroups);
        static::assertArrayNotHasKey('target_group', $cookieGroups[0]);
    }

    public function testParsesTargetGroupAttribute(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <cookies>
        <group target-group="cookie.groupMarketing">
            <snippet-name>test.group</snippet-name>
        </group>
    </cookies>
</manifest>
XML;

        $document = new \DOMDocument();
        $document->loadXML($xml);
        $cookiesElement = $document->getElementsByTagName('cookies')->item(0);
        static::assertInstanceOf(\DOMElement::class, $cookiesElement);

        $cookies = Cookies::fromXml($cookiesElement);

        $cookieGroups = $cookies->getCookies();
        static::assertCount(1, $cookieGroups);
        static::assertArrayHasKey('target_group', $cookieGroups[0]);
        static::assertSame('cookie.groupMarketing', $cookieGroups[0]['target_group']);
    }
}
