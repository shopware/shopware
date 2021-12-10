<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Entity\Xml\Field;

class LabelField extends Field
{
    protected string $type = 'label';

    public static function fromXml(\DOMElement $element): Field
    {
        return new self(self::parse($element));
    }
}
