<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class DiscountSurchargeTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $discountSurchargeId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var DiscountSurchargeStruct|null
     */
    protected $discountSurcharge;

    /**
     * @var LanguageStruct|null
     */
    protected $language;

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

    public function getDiscountSurchargeId(): string
    {
        return $this->discountSurchargeId;
    }

    public function setDiscountSurchargeId(string $discountSurchargeId): void
    {
        $this->discountSurchargeId = $discountSurchargeId;
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

    public function getDiscountSurcharge(): ?DiscountSurchargeStruct
    {
        return $this->discountSurcharge;
    }

    public function setDiscountSurcharge(DiscountSurchargeStruct $discountSurcharge): void
    {
        $this->discountSurcharge = $discountSurcharge;
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
