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
#[Package('buyers-experience')]
final class Tab extends ConfigXmlElement
{
    /**
     * @var list<Card>
     */
    protected array $cards;

    protected string $name;

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

    protected static function parse(\DOMElement $element): array
    {
        $cards = [];
        foreach ($element->getElementsByTagName('card') as $card) {
            $cards[] = Card::fromXml($card);
        }

        return [
            'cards' => $cards,
            'name' => XmlUtils::phpize($element->getAttribute('name')),
        ];
    }
}
