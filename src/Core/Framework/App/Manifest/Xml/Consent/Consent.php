<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Consent;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class Consent extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'name',
        'scope',
        'label',
    ];

    private const TRANSLATABLE_FIELDS = [
        'label',
        'description',
    ];

    protected string $name;

    protected string $scope;

    /**
     * @var array<string, string>
     */
    protected array $label = [];

    /**
     * @var array<string, string>
     */
    protected array $description = [];

    protected ?string $revision = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function getScope(): string
    {
        return $this->scope;
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

    public function getRevision(): ?string
    {
        return $this->revision;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array
    {
        $data = parent::toArray($defaultLocale);

        foreach (self::TRANSLATABLE_FIELDS as $field) {
            $data[$field] = $this->ensureTranslationForDefaultLanguageExist($data[$field], $defaultLocale);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function parse(\DOMElement $element): array
    {
        return XmlParserUtils::parseChildrenAndTranslate($element, self::TRANSLATABLE_FIELDS);
    }
}
