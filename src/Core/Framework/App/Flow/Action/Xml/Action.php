<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Flow\Action\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class Action extends XmlElement
{
    protected Metadata $meta;

    protected Headers $headers;

    protected Parameters $parameters;

    protected Config $config;

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

    public function toArray(string $defaultLocale): array
    {
        $data = parent::toArray($defaultLocale);

        return array_merge($data, [
            'name' => $this->meta->getName(),
            'swIcon' => $this->meta->getSwIcon(),
            'url' => $this->meta->getUrl(),
            'delayable' => $this->meta->getDelayable(),
            'parameters' => $this->normalizeParameters(),
            'config' => array_map(fn (InputField $config) => $config->jsonSerialize(), $this->config->getConfig()),
            'headers' => array_map(fn (Parameter $header) => $header->jsonSerialize(), $this->headers->getParameters()),
            'requirements' => $this->meta->getRequirements(),
            'label' => $this->meta->getLabel(),
            'description' => $this->meta->getDescription(),
            'headline' => $this->meta->getHeadline(),
        ]);
    }

    protected static function parse(\DOMElement $element): array
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

    /**
     * @return array<string, string>
     */
    private function normalizeParameters(): array
    {
        $parameters = array_map(fn (Parameter $parameter) => $parameter->jsonSerialize(), $this->parameters->getParameters());

        $parameters = json_encode($parameters, \JSON_THROW_ON_ERROR);

        $parameters = (string) \preg_replace('/\\\\([a-zA-Z])/', '$1', $parameters);

        return json_decode($parameters, true, 512, \JSON_THROW_ON_ERROR);
    }
}
