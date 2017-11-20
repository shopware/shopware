<?php declare(strict_types=1);

namespace Shopware\Payment\Struct;

use Shopware\Api\Entity\Entity;

class PaymentMethodTranslationBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $paymentMethodUuid;

    /**
     * @var string
     */
    protected $languageUuid;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $additionalDescription;

    public function getPaymentMethodUuid(): string
    {
        return $this->paymentMethodUuid;
    }

    public function setPaymentMethodUuid(string $paymentMethodUuid): void
    {
        $this->paymentMethodUuid = $paymentMethodUuid;
    }

    public function getLanguageUuid(): string
    {
        return $this->languageUuid;
    }

    public function setLanguageUuid(string $languageUuid): void
    {
        $this->languageUuid = $languageUuid;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAdditionalDescription(): string
    {
        return $this->additionalDescription;
    }

    public function setAdditionalDescription(string $additionalDescription): void
    {
        $this->additionalDescription = $additionalDescription;
    }
}
