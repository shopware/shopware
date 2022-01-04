<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\System\CustomEntity\Xml\Field\Traits\ReferenceTrait;
use Shopware\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;

class ManyToOneField extends Field
{
    use RequiredTrait;
    use ReferenceTrait;

    protected string $type = 'many-to-one';

    public static function fromXml(\DOMElement $element): Field
    {
        return new self(self::parse($element));
    }
}
