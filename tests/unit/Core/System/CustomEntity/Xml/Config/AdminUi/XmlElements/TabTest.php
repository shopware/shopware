<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Card;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Tab;

/**
 * @internal
 */
#[CoversClass(Tab::class)]
class TabTest extends TestCase
{
    public function testFromXml(): void
    {
        $dom = new \DOMDocument();
        $tabElement = $dom->createElement('tab');
        $tabElement->setAttribute('name', 'TabTest');
        $cardElement = $dom->createElement('card');

        $tabElement->appendChild(
            $cardElement
        );

        $tab = Tab::fromXml($tabElement);
        static::assertSame('TabTest', $tab->getName());

        $cardsList = $tab->getCards();
        static::assertInstanceOf(Card::class, \array_pop($cardsList));
    }
}
