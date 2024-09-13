<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Detail;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Tabs;

/**
 * @internal
 */
#[CoversClass(Detail::class)]
class DetailTest extends TestCase
{
    public function testFromXml(): void
    {
        $dom = new \DOMDocument();
        $detailElement = $dom->createElement('detail');
        $tabsElement = $dom->createElement('tabs');

        $detailElement->appendChild(
            $tabsElement
        );

        $detail = Detail::fromXml($detailElement);
        static::assertInstanceOf(Detail::class, $detail);

        $tabs = $detail->getTabs();
        static::assertInstanceOf(Tabs::class, $tabs);
    }
}
