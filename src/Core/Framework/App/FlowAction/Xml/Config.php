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
class Config extends XmlElement
{
    /**
     * @param InputField[] $config
     */
    public function __construct(protected array $config)
    {
    }

    /**
     * @return InputField[]
     */
    public function getConfig(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Config')
        );

        return $this->config;
    }

    public static function fromXml(\DOMElement $element): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Config')
        );

        return new self(self::parseInputField($element));
    }

    /**
     * @return array<int, InputField>
     */
    private static function parseInputField(\DOMElement $element): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Config')
        );

        $values = [];

        foreach ($element->getElementsByTagName('input-field') as $parameter) {
            $values[] = InputField::fromXml($parameter);
        }

        return $values;
    }
}
