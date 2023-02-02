<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal
 */
class Config extends XmlElement
{
    /**
     * @var InputField[]
     */
    protected array $config;

    public function __construct(array $data)
    {
        $this->config = $data;
    }

    /**
     * @return InputField[]
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parseInputField($element));
    }

    private static function parseInputField(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->getElementsByTagName('input-field') as $parameter) {
            $values[] = InputField::fromXml($parameter);
        }

        return $values;
    }
}
