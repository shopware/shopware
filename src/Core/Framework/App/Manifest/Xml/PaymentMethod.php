<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class PaymentMethod extends XmlElement
{
    final public const TRANSLATABLE_FIELDS = ['name', 'description'];

    final public const REQUIRED_FIELDS = [
        'identifier',
        'name',
    ];

    protected string $identifier;

    /**
     * @var array<string>
     */
    protected array $name = [];

    /**
     * @var array<string>
     */
    protected array $description = [];

    protected ?string $payUrl = null;

    protected ?string $finalizeUrl = null;

    protected ?string $validateUrl = null;

    protected ?string $captureUrl = null;

    protected ?string $refundUrl = null;

    protected ?string $icon = null;

    private function __construct(array $data)
    {
        $this->validateRequiredElements($data, self::REQUIRED_FIELDS);

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
            'validateUrl' => $data['validateUrl'],
            'captureUrl' => $data['captureUrl'],
            'refundUrl' => $data['refundUrl'],
        ];

        unset(
            $data['identifier'],
            $data['payUrl'],
            $data['finalizeUrl'],
            $data['validateUrl'],
            $data['captureUrl'],
            $data['refundUrl'],
            $data['icon']
        );

        return $data;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return array<string>
     */
    public function getName(): array
    {
        return $this->name;
    }

    /**
     * @return array<string>
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

    public function getValidateUrl(): ?string
    {
        return $this->validateUrl;
    }

    public function getCaptureUrl(): ?string
    {
        return $this->captureUrl;
    }

    public function getRefundUrl(): ?string
    {
        return $this->refundUrl;
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
