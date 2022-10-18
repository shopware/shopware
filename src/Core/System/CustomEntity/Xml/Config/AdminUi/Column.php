<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi;

use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityFlag;

/**
 * @internal
 */
class Column extends CustomEntityFlag
{
    public static function fromXml(\DOMElement $element): CustomEntityFlag
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }
}
