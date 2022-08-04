<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomEntity\Xml\Flag\AdminUi;

use Shopware\Core\System\CustomEntity\Xml\Flag\Flag;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 */
class CardFieldConfig extends Flag
{
    public static function fromXml(\DOMElement $element): Flag
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
