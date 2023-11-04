<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\FlowAction\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @deprecated tag:v6.6.0 - Will be move to Shopware\Core\Framework\App\Flow\Action\Xml
 */
#[Package('core')]
class Action extends XmlElement
{
    protected Metadata $meta;

    protected Headers $headers;

    protected Parameters $parameters;

    protected Config $config;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public function getMeta(): Metadata
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Action')
        );

        return $this->meta;
    }

    public function getHeaders(): Headers
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Action')
        );

        return $this->headers;
    }

    public function getParameters(): Parameters
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Action')
        );

        return $this->parameters;
    }

    public function getConfig(): Config
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Action')
        );

        return $this->config;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Action')
        );

        $data = parent::toArray($defaultLocale);

        return array_merge($data, [
            'name' => $this->meta->getName(),
            'swIcon' => $this->meta->getSwIcon(),
            'url' => $this->meta->getUrl(),
            'delayable' => $this->meta->getDelayable(),
            'parameters' => $this->normalizeParameters(),
            'config' => array_map(fn ($config) => $config->jsonSerialize(), $this->config->getConfig()),
            'headers' => array_map(fn ($header) => $header->jsonSerialize(), $this->headers->getParameters()),
            'requirements' => $this->meta->getRequirements(),
            'label' => $this->meta->getLabel(),
            'description' => $this->meta->getDescription(),
            'headline' => $this->meta->getHeadline(),
        ]);
    }

    public static function fromXml(\DOMElement $element): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', '\Shopware\Core\Framework\App\Flow\Action\Xml\Action')
        );

        return new self(self::parse($element));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeParameters(): array
    {
        /** @var array<string, mixed> $parameters */
        $parameters = array_map(fn ($parameter) => $parameter->jsonSerialize(), $this->parameters->getParameters());

        $parameters = json_encode($parameters, \JSON_THROW_ON_ERROR);

        /** @var string $parameters */
        $parameters = \preg_replace('/\\\\([a-zA-Z])/', '$1', $parameters);

        return json_decode($parameters, true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
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
