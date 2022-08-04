<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Flag\AdminUi;

use Shopware\Core\System\CustomEntity\Xml\Flag\Flag;

/**
 * @internal
 */
class ColumnConfig extends Flag
{
    public static function fromXml(\DOMElement $element): Flag
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }
}
