<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - Will be move to Shopware\Core\Framework\App\Flow\Action\Xml
 */
#[Package('core')]
class Parameter extends XmlElement
{
    protected string $type;

    protected string $name;

    protected string $value;

    public function getType(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Parameter')
        );

        return $this->type;
    }

    public function getName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Parameter')
        );

        return $this->name;
    }

    public function getValue(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Parameter')
        );

        return $this->value;
    }

    public static function fromXml(\DOMElement $element): static
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Parameter')
        );

        return parent::fromXml($element);
    }

    protected static function parse(\DOMElement $element): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Parameter')
        );

        $values = [];

        /** @var \DOMNamedNodeMap $attributes */
        $attributes = $element->attributes;

        /** @var \DOMAttr $item */
        foreach ($attributes as $item) {
            $values[$item->name] = XmlUtils::phpize($item->value);
        }

        return $values;
    }
}
