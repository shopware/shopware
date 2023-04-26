<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Field;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class OneToManyField extends AssociationField
{
    protected string $type = 'one-to-many';

    protected bool $reverseRequired = false;

    public static function fromXml(\DOMElement $element): Field
    {
        return new self(self::parse($element));
    }

    public function isReverseRequired(): bool
    {
        return $this->reverseRequired;
    }
}
