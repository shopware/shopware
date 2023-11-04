<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class LabelField extends Field
{
    protected string $type = 'label';

    /**
     * @internal
     */
    public static function fromXml(\DOMElement $element): Field
    {
        return new self(self::parse($element));
    }
}
