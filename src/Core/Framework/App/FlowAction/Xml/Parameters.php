<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - Will be move to Shopware\Core\Framework\App\Flow\Action\Xml
 */
#[Package('core')]
class Parameters extends XmlElement
{
    /**
     * @var Parameter[]
     */
    protected array $parameters;

    /**
     * @return Parameter[]
     */
    public function getParameters(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Parameters')
        );

        return $this->parameters;
    }

    public static function fromXml(\DOMElement $element): static
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Parameters')
        );

        return parent::fromXml($element);
    }

    protected static function parse(\DOMElement $element): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Parameters')
        );

        $values = [];

        foreach ($element->getElementsByTagName('parameter') as $parameter) {
            $values[] = Parameter::fromXml($parameter);
        }

        return ['parameters' => $values];
    }
}
