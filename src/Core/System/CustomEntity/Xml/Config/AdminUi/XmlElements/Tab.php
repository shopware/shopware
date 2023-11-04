<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * Represents the XML tab element
 *
 * admin-ui > entity > detail > tabs > tab
 *
 * @internal
 */
#[Package('content')]
final class Tab extends ConfigXmlElement
{
    /**
     * @param list<Card> $cards
     */
    private function __construct(
        protected readonly array $cards,
        protected readonly string $name
    ) {
    }

    public static function fromXml(\DOMElement $element): self
    {
        $cards = [];
        foreach ($element->getElementsByTagName('card') as $card) {
            $cards[] = Card::fromXml($card);
        }

        return new self(
            $cards,
            XmlUtils::phpize($element->getAttribute('name'))
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<Card>
     */
    public function getCards(): array
    {
        return $this->cards;
    }
}
