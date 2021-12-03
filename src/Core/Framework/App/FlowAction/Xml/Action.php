<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;

/**
 * @internal (flag:FEATURE_NEXT_17540) - only for use by the app-system
 */
class Action extends XmlElement
{
    protected Metadata $meta;

    protected Headers $headers;

    protected Parameters $parameters;

    protected Config $config;

    public function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public function getMeta(): Metadata
    {
        return $this->meta;
    }

    public function getHeaders(): Headers
    {
        return $this->headers;
    }

    public function getParameters(): Parameters
    {
        return $this->parameters;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->getElementsByTagName('meta') as $meta) {
            $values['meta'] = Metadata::fromXml($meta);
        }

        foreach ($element->getElementsByTagName('headers') as $header) {
            $values['headers'] = Headers::fromXml($header);
        }

        foreach ($element->getElementsByTagName('parameters') as $parameter) {
            $values['parameters'] = Parameters::fromXml($parameter);
        }

        foreach ($element->getElementsByTagName('config') as $config) {
            $values['config'] = Config::fromXml($config);
        }

        return $values;
    }
}
