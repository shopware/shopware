<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Manifest\Xml\PaymentMethod;

use Shopware\Core\Framework\App\Manifest\Xml\XmlElement;
use Shopware\Core\Framework\App\Manifest\XmlParserUtils;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class PaymentMethod extends XmlElement
{
    protected const REQUIRED_FIELDS = [
        'identifier',
        'name',
    ];

    private const TRANSLATABLE_FIELDS = [
        'name',
        'description',
    ];

    protected string $identifier;

    /**
     * @var array<string, string>
     */
    protected array $name = [];

    /**
     * @var array<string, string>
     */
    protected array $description = [];

    protected ?string $payUrl = null;

    protected ?string $finalizeUrl = null;

    protected ?string $validateUrl = null;

    protected ?string $captureUrl = null;

    protected ?string $refundUrl = null;

    protected ?string $recurringUrl = null;

    protected ?string $icon = null;

    /**
     * @return array<string, mixed>
     */
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
            'recurringUrl' => $data['recurringUrl'],
        ];

        unset(
            $data['identifier'],
            $data['payUrl'],
            $data['finalizeUrl'],
            $data['validateUrl'],
            $data['captureUrl'],
            $data['refundUrl'],
            $data['recurringUrl'],
            $data['icon']
        );

        return $data;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * @return array<string, string>
     */
    public function getName(): array
    {
        return $this->name;
    }

    /**
     * @return array<string, string>
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

    public function getRecurringUrl(): ?string
    {
        return $this->recurringUrl;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    protected static function parse(\DOMElement $element): array
    {
        return XmlParserUtils::parseChildrenAndTranslate($element, self::TRANSLATABLE_FIELDS);
    }
}
