<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;

/**
 * Represents the XML tabs element
 *
 * admin-ui > entity > detail > tabs
 *
 * @internal
 */
#[Package('content')]
final class Tabs extends ConfigXmlElement
{
    /**
     * @param list<Tab> $content
     */
    private function __construct(
        protected readonly array $content,
    ) {
    }

    public static function fromXml(\DOMElement $element): self
    {
        $tabs = [];
        foreach ($element->getElementsByTagName('tab') as $tab) {
            $tabs[] = Tab::fromXml($tab);
        }

        return new self($tabs);
    }

    /**
     * @return list<Tab>
     */
    public function getContent(): array
    {
        return $this->content;
    }

    public function jsonSerialize(): array
    {
        $data = parent::jsonSerialize();

        return $data['content'];
    }
}
