<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Entity\Xml\Field;

use Shopware\Core\Framework\App\Entity\Xml\Field\Traits\ReferenceTrait;

class OneToManyField extends Field
{
    use ReferenceTrait;

    protected string $type = 'one-to-many';

    public static function fromXml(\DOMElement $element): Field
    {
        return new self(self::parse($element));
    }
}
