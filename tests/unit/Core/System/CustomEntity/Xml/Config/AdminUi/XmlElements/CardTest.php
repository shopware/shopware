<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\Card;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\CardField;

/**
 * @package content
 *
 * @internal
 */
#[CoversClass(Card::class)]
class CardTest extends TestCase
{
    public function testFromXml(): void
    {
        $dom = new \DOMDocument();
        $cardElement = $dom->createElement('card');
        $cardElement->setAttribute('name', 'CardTest');

        $cardFieldElement = $dom->createElement('field');

        $cardElement->appendChild(
            $cardFieldElement
        );

        $card = Card::fromXml($cardElement);

        $cardFields = $card->getFields();
        static::assertInstanceOf(Card::class, $card);
        static::assertIsArray($cardFields);
        static::assertInstanceOf(CardField::class, \array_pop($cardFields));
    }
}
