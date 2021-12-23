<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Entity\Xml\Field;

use Shopware\Core\Framework\App\Entity\Xml\Field\Traits\ReferenceTrait;
use Shopware\Core\Framework\App\Entity\Xml\Field\Traits\RequiredTrait;

class OneToOneField extends Field
{
    use ReferenceTrait;
    use RequiredTrait;

    protected string $type = 'one-to-one';

    public static function fromXml(\DOMElement $element): Field
    {
        return new self(self::parse($element));
    }
}
