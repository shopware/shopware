<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;

class PriceField extends Field
{
    use RequiredTrait;

    protected string $type = 'price';

    /**
     * @internal
     */
    public static function fromXml(\DOMElement $element): Field
    {
        return new self(self::parse($element));
    }
}
