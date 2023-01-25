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
#[Package('content')]
final class Entity extends ConfigXmlElement
{
    private function __construct(
        protected readonly Listing $listing,
        protected readonly Detail $detail,
        protected readonly string $name,
        protected readonly string $icon,
        protected readonly string $color,
        protected readonly int $position,
        protected readonly string $navigationParent,
    ) {
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(
            Listing::fromXml($element),
            Detail::fromXml($element),
            XmlUtils::phpize($element->getAttribute('name')),
            XmlUtils::phpize($element->getAttribute('icon')),
            XmlUtils::phpize($element->getAttribute('color')),
            XmlUtils::phpize($element->getAttribute('position')),
            XmlUtils::phpize($element->getAttribute('navigation-parent')),
        );
    }

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
}
