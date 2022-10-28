<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityFlag;

/**
 * Represents the XML column element
 *
 * admin-ui > entity > listing > columns > column
 *
 * @internal
 */
class Column extends CustomEntityFlag
{
    protected string $ref;

    public function getRef(): string
    {
        return $this->ref;
    }

    public static function fromXml(\DOMElement $element): CustomEntityFlag
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }
}
