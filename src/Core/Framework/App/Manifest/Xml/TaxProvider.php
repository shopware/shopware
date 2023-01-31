<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('checkout')]
class TaxProvider extends XmlElement
{
    final public const REQUIRED_FIELDS = [
        'identifier',
        'name',
        'processUrl',
        'priority',
    ];

    protected string $identifier;

    protected string $name;

    protected string $processUrl;

    protected string $priority;

    /**
     * @param array<string, mixed> $data
     */
    private function __construct(array $data)
    {
        $this->validateRequiredElements($data, self::REQUIRED_FIELDS);

        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getProcessUrl(): string
    {
        return $this->processUrl;
    }

    public function getPriority(): int
    {
        return (int) $this->priority;
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    /**
     * @return array<string, string|null>
     */
    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            $values[self::kebabCaseToCamelCase($child->tagName)] = $child->nodeValue;
        }

        return $values;
    }
}
