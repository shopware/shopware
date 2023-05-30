<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Cms\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('content')]
class DefaultConfig extends XmlElement
{
    protected ?string $marginTop = null;

    protected ?string $marginRight = null;

    protected ?string $marginBottom = null;

    protected ?string $marginLeft = null;

    protected ?string $sizingMode = null;

    protected ?string $backgroundColor = null;

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
