<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal
 */
class Headers extends XmlElement
{
    /**
     * @var Parameter[]
     */
    protected array $parameters;

    public function __construct(array $data)
    {
        $this->parameters = $data;
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

        foreach ($element->getElementsByTagName('parameter') as $parameter) {
            $values[] = Parameter::fromXml($parameter);
        }

        return $values;
    }
}
