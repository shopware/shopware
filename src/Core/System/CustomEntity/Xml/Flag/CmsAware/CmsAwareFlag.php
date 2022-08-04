<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Flag\CmsAware;

use Shopware\Core\System\CustomEntity\Xml\Flag\Flag;

/**
 * @internal
 */
class CmsAwareFlag extends Flag
{
    protected string $technicalName = 'cms-aware';

    public static function fromXml(\DOMElement $element): Flag
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }
}
