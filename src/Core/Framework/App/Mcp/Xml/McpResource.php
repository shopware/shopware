<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Mcp\Xml;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class McpResource extends XmlElement implements McpCapabilityItem
{
    protected const REQUIRED_FIELDS = [
        'name',
        'uri',
        'url',
        'label',
    ];

    private const TRANSLATABLE_FIELDS = [
        'label',
        'description',
    ];

    protected string $name;

    protected string $uri;

    protected string $url;

    protected ?string $mimeType = null;

    /**
     * @var array<string, string>
     */
    protected array $label = [];

    /**
     * @var array<string, string>
     */
    protected array $description = [];

    public function getName(): string
    {
        return $this->name;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
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

        return $values;
    }
}
