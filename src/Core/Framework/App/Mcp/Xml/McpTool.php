<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Mcp\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class McpTool extends XmlElement implements McpCapabilityItem
{
    protected const REQUIRED_FIELDS = [
        'name',
        'url',
        'label',
    ];

    private const TRANSLATABLE_FIELDS = [
        'label',
        'description',
    ];

    protected string $name;

    protected string $url;

    /**
     * @var list<string>
     */
    protected array $requiredPrivileges = [];

    /**
     * @var array<string, string>
     */
    protected array $label = [];

    /**
     * @var array<string, string>
     */
    protected array $description = [];

    /**
     * @var array<string, array{type: string, description?: string, required?: bool}>|null
     */
    protected ?array $inputSchema = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return list<string>
     */
    public function getRequiredPrivileges(): array
    {
        return $this->requiredPrivileges;
    }

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    /**
     * @return array<string, string>
     */
    public function getDescription(): array
    {
        return $this->description;
    }

    /**
     * @return array<string, array{type: string, description?: string, required?: bool}>|null
     */
    public function getInputSchema(): ?array
    {
        return $this->inputSchema;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array
    {
        $data = parent::toArray($defaultLocale);

        foreach (self::TRANSLATABLE_FIELDS as $field) {
            $camelField = self::kebabCaseToCamelCase($field);

            $data[$camelField] = $this->ensureTranslationForDefaultLanguageExist(
                $data[$camelField],
                $defaultLocale,
            );
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function parse(\DOMElement $element): array
    {
        $values = XmlParserUtils::parseAttributes($element);

        $values += XmlParserUtils::parseChildrenAndTranslate($element, self::TRANSLATABLE_FIELDS);

        $inputSchema = $element->getElementsByTagName('input-schema')->item(0);
        if ($inputSchema instanceof \DOMElement) {
            $values['inputSchema'] = self::parseInputSchema($inputSchema);
        }

        $requiredPrivileges = $element->getElementsByTagName('required-privileges')->item(0);
        if ($requiredPrivileges instanceof \DOMElement) {
            $privileges = [];
            foreach ($requiredPrivileges->getElementsByTagName('privilege') as $node) {
                $text = trim($node->textContent);
                if ($text !== '') {
                    $privileges[] = $text;
                }
            }
            if ($privileges !== []) {
                $values['requiredPrivileges'] = $privileges;
            }
        }

        return $values;
    }

    /**
     * @return array<string, array{type: string, description?: string, required?: bool}>
     */
    private static function parseInputSchema(\DOMElement $element): array
    {
        $properties = [];

        foreach ($element->getElementsByTagName('property') as $property) {
            \assert($property instanceof \DOMElement);

            $name = $property->getAttribute('name');

            if ($name === '') {
                continue;
            }

            $entry = ['type' => $property->getAttribute('type') ?: 'string'];

            if ($property->hasAttribute('description')) {
                $entry['description'] = $property->getAttribute('description');
            }

            if ($property->hasAttribute('required')) {
                $entry['required'] = $property->getAttribute('required') === 'true';
            }

            $properties[$name] = $entry;
        }

        return $properties;
    }
}
