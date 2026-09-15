<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\Storefront;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class SeoUrl extends XmlElement
{
    protected const REQUIRED_FIELDS = ['name'];

    private const TRANSLATABLE_FIELDS = ['label', 'path'];

    protected string $name;

    protected ?string $hook = null;

    protected ?string $entity = null;

    /**
     * @var array<string, string>
     */
    protected array $label = [];

    /**
     * @var array<string, string>
     */
    protected array $path = [];

    protected ?string $defaultTemplate = null;

    public function getName(): string
    {
        return $this->name;
    }

    public function getHook(): string
    {
        return $this->hook ?? $this->name;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
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
    public function getPath(): array
    {
        return $this->path;
    }

    public function getDefaultTemplate(): ?string
    {
        return $this->defaultTemplate;
    }

    public function toArray(string $defaultLocale): array
    {
        $data = parent::toArray($defaultLocale);

        foreach (self::TRANSLATABLE_FIELDS as $field) {
            $data[$field] = $this->ensureTranslationForDefaultLanguageExist($data[$field], $defaultLocale);
        }

        $data['hook'] = $this->getHook();
        $data['entityName'] = $data['entity'];
        $data['paths'] = $data['path'] ?: null;
        $data['label'] = $data['label'] ?: null;

        unset($data['entity'], $data['path']);

        return $data;
    }

    protected static function parse(\DOMElement $element): array
    {
        $values = ['name' => $element->getAttribute('name')];

        foreach (['hook', 'entity'] as $attribute) {
            if ($element->hasAttribute($attribute)) {
                $values[$attribute] = $element->getAttribute($attribute);
            }
        }

        $values += XmlParserUtils::parseChildrenAndTranslate($element, self::TRANSLATABLE_FIELDS);

        return $values;
    }
}
