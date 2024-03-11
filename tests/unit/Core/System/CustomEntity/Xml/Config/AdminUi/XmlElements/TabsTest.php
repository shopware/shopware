<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Tab;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Tabs;

/**
 * @package content
 *
 * @internal
 */
#[CoversClass(Tabs::class)]
class TabsTest extends TestCase
{
    public function testFromXml(): void
    {
        $dom = new \DOMDocument();
        $tabsElement = $dom->createElement('tabs');
        $tabElement = $dom->createElement('tab');

        $tabsElement->appendChild(
            $tabElement
        );

        $tabs = Tabs::fromXml($tabsElement);
        static::assertInstanceOf(Tabs::class, $tabs);

        $tabsList = $tabs->getContent();
        static::assertIsArray($tabsList);
        static::assertInstanceOf(Tab::class, \array_pop($tabsList));
    }

    public function testJsonSerialize(): void
    {
        $dom = new \DOMDocument();
        $tabsElement = $dom->createElement('tabs');
        $tabElement0 = $dom->createElement('tab');
        $tabElement1 = $dom->createElement('tab');

        $tabsElement->appendChild(
            $tabElement0
        );
        $tabsElement->appendChild(
            $tabElement1
        );

        $tabs = Tabs::fromXml($tabsElement);

        $serializedTabs = $tabs->jsonSerialize();

        static::assertEquals(
            [
                0 => Tab::fromXml($tabElement0),
                1 => Tab::fromXml($tabElement1),
            ],
            $serializedTabs
        );
    }
}
