<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * Represents the XML entity element
 *
 * admin-ui > entity
 *
 * @internal
 */
#[Package('buyers-experience')]
final class Entity extends ConfigXmlElement
{
    protected Listing $listing;

    protected Detail $detail;

    protected string $name;

    protected string $icon;

    protected string $color;

    protected int $position;

    protected string $navigationParent;

    public function getDetail(): Detail
    {
        return $this->detail;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getListing(): Listing
    {
        return $this->listing;
    }

    protected static function parse(\DOMElement $element): array
    {
        return [
            'listing' => Listing::fromXml($element),
            'detail' => Detail::fromXml($element),
            'name' => XmlUtils::phpize($element->getAttribute('name')),
            'icon' => XmlUtils::phpize($element->getAttribute('icon')),
            'color' => XmlUtils::phpize($element->getAttribute('color')),
            'position' => XmlUtils::phpize($element->getAttribute('position')),
            'navigationParent' => XmlUtils::phpize($element->getAttribute('navigation-parent')),
        ];
    }
}
