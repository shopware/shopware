<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('content')]
class Slot extends XmlElement
{
    protected string $name;

    protected string $type;

    protected Config $config;

    public function toArray(string $defaultLocale): array
    {
        $array = parent::toArray($defaultLocale);
        $array['config'] = $this->config->toArray($defaultLocale);

        return $array;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    protected static function parse(\DOMElement $element): array
    {
        $name = $element->getAttribute('name');
        $type = $element->getAttribute('type');
        $config = $element->getElementsByTagName('config')->item(0);
        \assert($config !== null);
        $config = Config::fromXml($config);

        return [
            'name' => $name,
            'type' => $type,
            'config' => $config,
        ];
    }
}
