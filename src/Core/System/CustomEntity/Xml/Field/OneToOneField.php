<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\System\CustomEntity\Xml\Field\Traits\RequiredTrait;

class OneToOneField extends AssociationField
{
    use RequiredTrait;

    protected string $type = 'one-to-one';

    /**
     * @internal
     */
    public static function fromXml(\DOMElement $element): Field
    {
        return new self(self::parse($element));
    }
}
