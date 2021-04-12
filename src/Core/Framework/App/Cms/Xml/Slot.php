<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 */
class Slot extends XmlElement
{
    protected string $name;

    protected string $type;

    protected Config $config;

    private function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseSlot($element));
    }

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

    private static function parseSlot(\DOMElement $element): array
    {
        $name = $element->getAttribute('name');
        $type = $element->getAttribute('type');
        /** @var \DOMElement $config */
        $config = $element->getElementsByTagName('config')->item(0);
        $config = Config::fromXml($config);

        return [
            'name' => $name,
            'type' => $type,
            'config' => $config,
        ];
    }
}
