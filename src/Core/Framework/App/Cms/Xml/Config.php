<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @package content
 *
 * @internal
 */
class Config extends XmlElement
{
    private function __construct(protected array $items)
    {
    }

    public function toArray(string $defaultLocale): array
    {
        return $this->items;
    }

    public static function fromXml(\DOMElement $element): self
    {
        $config = [];

        foreach ($element->getElementsByTagName('config-value') as $configValue) {
            $config[self::kebabCaseToCamelCase($configValue->getAttribute('name'))] = [
                'source' => $configValue->getAttribute('source'),
                'value' => $configValue->getAttribute('value'),
            ];
        }

        return new self($config);
    }
}
