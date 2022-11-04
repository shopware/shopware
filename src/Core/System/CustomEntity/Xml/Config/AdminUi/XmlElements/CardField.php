<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Config\AdminUi\XmlElements;

use Shopware\Core\System\CustomEntity\Xml\Config\CustomEntityFlag;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * Represents the XML field element
 *
 * admin-ui > entity > detail > tabs > tab > card > field
 *
 * @internal
 */
class CardField extends CustomEntityFlag
{
    public static function fromXml(\DOMElement $element): CustomEntityFlag
    {
        $self = new self();
        $self->assign($self->parse($element));

        return $self;
    }

    /**
     * @return array<string, mixed>
     */
    protected function parse(\DOMElement $element): array
    {
        $values = [];

        if (is_iterable($element->attributes)) {
            foreach ($element->attributes as $attribute) {
                $name = self::kebabCaseToCamelCase($attribute->name);

                $values[$name] = XmlUtils::phpize($attribute->value);

                if ($name === 'ref' && !\is_string($values[$name])) {
                    $values[$name] = (string) $values[$name];
                }
            }
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values = $this->parseChild($child, $values);
        }

        return $values;
    }
}
