<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\CmsAware\XmlElements;

use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityFlag;

class Entity extends CustomEntityFlag
{
    protected string $name;

    public function getName(): string
    {
        return $this->name;
    }

    public static function fromXml(\DOMElement $element): Entity
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }
}
