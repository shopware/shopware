<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Entity\Xml\Field;

use Shopware\Core\Framework\App\Entity\Xml\Field\Traits\RequiredTrait;
use Shopware\Core\Framework\App\Entity\Xml\Field\Traits\TranslatableTrait;

class IntField extends Field
{
    use TranslatableTrait;
    use RequiredTrait;

    protected string $type = 'int';

    public static function fromXml(\DOMElement $element): Field
    {
        return new self(self::parse($element));
    }
}
