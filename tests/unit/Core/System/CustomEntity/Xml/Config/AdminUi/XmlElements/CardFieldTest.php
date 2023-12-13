<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements\CardField;

/**
 * @package content
 *
 * @internal
 */
#[CoversClass(CardField::class)]
class CardFieldTest extends TestCase
{
    public function testFromXml(): void
    {
        $dom = new \DOMDocument();
        $cardFieldElement = $dom->createElement('field');
        $cardFieldElement->setAttribute('ref', 'cardField ref');

        $cardField = CardField::fromXml($cardFieldElement);

        static::assertInstanceOf(CardField::class, $cardField);
        static::assertEquals('cardField ref', $cardField->getRef());
    }
}
