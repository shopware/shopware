<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;

/**
 * Represents the XML detail element
 *
 * admin-ui > entity > detail
 *
 * @internal
 */
#[Package('buyers-experience')]
final class Detail extends ConfigXmlElement
{
    protected Tabs $tabs;

    public function getTabs(): Tabs
    {
        return $this->tabs;
    }

    protected static function parse(\DOMElement $element): array
    {
        return ['tabs' => Tabs::fromXml($element)];
    }
}
