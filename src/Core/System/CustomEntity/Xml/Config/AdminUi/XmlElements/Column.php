<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * Represents the XML column element
 *
 * admin-ui > entity > listing > columns > column
 *
 * @package content
 *
 * @internal
 */
final class Column extends ConfigXmlElement
{
    private function __construct(
        protected readonly string $ref,
        protected readonly bool $hidden
    ) {
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(
            XmlUtils::phpize($element->getAttribute('ref')),
            $element->getAttribute('hidden') === 'true',
        );
    }

    public function getRef(): string
    {
        return $this->ref;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }
}
