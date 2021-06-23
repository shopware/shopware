<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Feature;
use Symfony\Component\Config\Util\XmlUtils;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
class ActionButton extends XmlElement
{
    public const TRANSLATABLE_FIELDS = ['label'];

    /**
     * @var array
     */
    protected $label = [];

    /**
     * @var string
     */
    protected $action;

    /**
     * @var string
     */
    protected $entity;

    /**
     * @var string
     */
    protected $view;

    /**
     * @var string
     */
    protected $url;

    /**
     * @var bool
     *
     * @feature-deprecated (FEATURE_NEXT_14360) tag:v6.5.0 - will be removed.
     * It will no longer be used in the manifest.xml file
     * and will be processed in the Executor with an OpenNewTabResponse response instead.
     */
    protected $openNewTab = false;

    private function __construct(array $data)
    {
        foreach ($data as $property => $value) {
            $this->$property = $value;
        }
    }

    public static function fromXml(\DOMElement $element): self
    {
        return new self(self::parse($element));
    }

    public function toArray(string $defaultLocale): array
    {
        $data = parent::toArray($defaultLocale);

        foreach (self::TRANSLATABLE_FIELDS as $TRANSLATABLE_FIELD) {
            $translatableField = self::kebabCaseToCamelCase($TRANSLATABLE_FIELD);

            $data[$translatableField] = $this->ensureTranslationForDefaultLanguageExist(
                $data[$translatableField],
                $defaultLocale
            );
        }

        return $data;
    }

    public function getLabel(): array
    {
        return $this->label;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function getView(): string
    {
        return $this->view;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @feature-deprecated (FEATURE_NEXT_14360) tag:v6.5.0 - Will be remove on version 6.5.0.
     * It will no longer be used in the manifest.xml file
     * and will be processed in the Executor with an OpenNewTabResponse response instead.
     */
    public function isOpenNewTab(): bool
    {
        if (Feature::isActive('FEATURE_NEXT_14360')) {
            throw new \Exception('Deprecated: isOpenNewTab property is deprecated...');
        }

        return $this->openNewTab;
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->attributes as $attribute) {
            $values[$attribute->name] = XmlUtils::phpize($attribute->value);
        }

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            // translated
            if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
                $values = self::mapTranslatedTag($child, $values);

                continue;
            }
        }

        return $values;
    }
}
