<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

/**
 * @internal only for use by the app-system
 */
class PaymentMethod extends XmlElement
{
    public const TRANSLATABLE_FIELDS = ['name', 'description'];

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var string[]
     */
    protected $name = [];

    /**
     * @var string[]
     */
    protected $description = [];

    /**
     * @var string|null
     */
    protected $payUrl;

    /**
     * @var string|null
     */
    protected $finalizeUrl;

    /**
     * @var string|null
     */
    protected $icon;

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

        $data['appPaymentMethod'] = [
            'identifier' => $data['identifier'],
            'payUrl' => $data['payUrl'],
            'finalizeUrl' => $data['finalizeUrl'],
        ];

        unset($data['identifier'], $data['payUrl'], $data['finalizeUrl'], $data['icon']);

        return $data;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return string[]
     */
    public function getName(): array
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getDescription(): array
    {
        return $this->description;
    }

    public function getPayUrl(): ?string
    {
        return $this->payUrl;
    }

    public function getFinalizeUrl(): ?string
    {
        return $this->finalizeUrl;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    private static function parse(\DOMElement $element): array
    {
        $values = [];

        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            // translated
            if (\in_array($child->tagName, self::TRANSLATABLE_FIELDS, true)) {
                $values = self::mapTranslatedTag($child, $values);

                continue;
            }

            $values[self::kebabCaseToCamelCase($child->tagName)] = $child->nodeValue;
        }

        return $values;
    }
}
