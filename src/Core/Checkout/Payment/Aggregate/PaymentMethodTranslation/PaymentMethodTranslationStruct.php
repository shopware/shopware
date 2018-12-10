<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation;

use Shopware\Core\Checkout\Payment\PaymentMethodStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Language\LanguageStruct;

class PaymentMethodTranslationStruct extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $paymentMethodId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $additionalDescription;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

    /**
     * @var PaymentMethodStruct
     */
    protected $paymentMethod;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getPaymentMethodId(): string
    {
        return $this->paymentMethodId;
    }

    public function setPaymentMethodId(string $paymentMethodId): void
    {
        $this->paymentMethodId = $paymentMethodId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getAdditionalDescription(): ?string
    {
        return $this->additionalDescription;
    }

    public function setAdditionalDescription(?string $additionalDescription): void
    {
        $this->additionalDescription = $additionalDescription;
    }

    public function getPaymentMethod(): PaymentMethodStruct
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(PaymentMethodStruct $paymentMethod): void
    {
        $this->paymentMethod = $paymentMethod;
    }

    public function getLanguage(): ?LanguageStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageStruct $language): void
    {
        $this->language = $language;
    }
}
