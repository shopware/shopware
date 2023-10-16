<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Xml\Config\ConfigXmlElement;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * Represents the XML column element
 *
 * admin-ui > entity > listing > columns > column
 *
 * @internal
 */
#[Package('content')]
final class Column extends ConfigXmlElement
{
    protected string $ref;

    protected bool $hidden;

    public function getRef(): string
    {
        return $this->ref;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    protected static function parse(\DOMElement $element): array
    {
        return [
            'ref' => XmlUtils::phpize($element->getAttribute('ref')),
            'hidden' => $element->getAttribute('hidden') === 'true',
        ];
    }
}
