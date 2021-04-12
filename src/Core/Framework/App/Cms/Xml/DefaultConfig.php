<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal (flag:FEATURE_NEXT_14408)
 */
class DefaultConfig extends XmlElement
{
    protected ?string $marginTop;

    protected ?string $marginRight;

    protected ?string $marginBottom;

    protected ?string $marginLeft;

    protected ?string $sizingMode;

    protected ?string $backgroundColor;

    private function __construct(array $defaultConfig)
    {
        foreach ($defaultConfig as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): self
    {
        $defaultConfig = [];

        foreach ($element->childNodes as $config) {
            if ($config instanceof \DOMText) {
                continue;
            }

            $defaultConfig[self::kebabCaseToCamelCase($config->nodeName)] = $config->nodeValue;
        }

        return new self($defaultConfig);
    }

    public function getMarginTop(): ?string
    {
        return $this->marginTop;
    }

    public function getMarginRight(): ?string
    {
        return $this->marginRight;
    }

    public function getMarginBottom(): ?string
    {
        return $this->marginBottom;
    }

    public function getMarginLeft(): ?string
    {
        return $this->marginLeft;
    }

    public function getSizingMode(): ?string
    {
        return $this->sizingMode;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }
}
