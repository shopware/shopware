<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 */
class Config extends XmlElement
{
    protected array $items = [];

    private function __construct(array $items)
    {
        $this->items = $items;
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
