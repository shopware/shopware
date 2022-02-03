<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\System\CustomEntity\Xml\Field\Traits\InheritedTrait;
use Shopware\Core\System\CustomEntity\Xml\Field\Traits\ReferenceTrait;
use Shopware\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;

class OneToOneField extends Field
{
    use ReferenceTrait;
    use RequiredTrait;
    use InheritedTrait;

    protected string $type = 'one-to-one';

    public static function fromXml(\DOMElement $element): Field
    {
        return new self(self::parse($element));
    }
}
