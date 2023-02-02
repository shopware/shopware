<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class Headers extends XmlElement
{
    /**
     * @param Parameter[] $parameters
     */
    public function __construct(protected array $parameters)
    {
    }

    /**
     * @return Parameter[]
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseParameter($element));
    }

    private static function parseParameter(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->getElementsByTagName('parameter') as $parameters) {
            $values[] = Parameter::fromXml($parameters);
        }

        return $values;
    }
}
