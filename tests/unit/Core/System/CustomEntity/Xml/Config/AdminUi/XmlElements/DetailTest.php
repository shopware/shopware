<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Detail;

/**
 * @internal
 */
#[Package('framework')]
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
        $tabs = $detail->getTabs();
        static::assertSame([], $tabs->getContent());
    }
}
