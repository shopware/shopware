<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\System\CustomEntity\Xml\Field\Traits\InheritedTrait;
use Shopware\Core\System\CustomEntity\Xml\Field\Traits\ReferenceTrait;

class OneToManyField extends Field
{
    use ReferenceTrait;
    use InheritedTrait;

    protected string $type = 'one-to-many';

    public static function fromXml(\DOMElement $element): Field
    {
        return new self(self::parse($element));
    }
}
