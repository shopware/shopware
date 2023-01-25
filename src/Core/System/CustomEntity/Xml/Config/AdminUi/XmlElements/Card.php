<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * Represents the XML card element
 *
 * admin-ui > entity > detail > tabs > tab > card
 *
 * @internal
 */
#[Package('content')]
final class Card extends ConfigXmlElement
{
    /**
     * @param list<CardField> $fields
     */
    private function __construct(
        protected readonly array $fields,
        protected readonly string $name
    ) {
    }

    public static function fromXml(\DOMElement $element): self
    {
        $fields = [];
        foreach ($element->getElementsByTagName('field') as $field) {
            $fields[] = CardField::fromXml($field);
        }

        return new self(
            $fields,
            XmlUtils::phpize($element->getAttribute('name'))
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<CardField>
     */
    public function getFields(): array
    {
        return $this->fields;
    }
}
