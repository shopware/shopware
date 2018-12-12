<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation;

use Shopware\Core\Checkout\DiscountSurcharge\DiscountSurchargeEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Language\LanguageEntity;

class DiscountSurchargeTranslationEntity extends Entity
{
    use EntityIdTrait;
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
     * @var DiscountSurchargeEntity|null
     */
    protected $discountSurcharge;

    /**
     * @var LanguageEntity|null
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

    public function getDiscountSurcharge(): ?DiscountSurchargeEntity
    {
        return $this->discountSurcharge;
    }

    public function setDiscountSurcharge(DiscountSurchargeEntity $discountSurcharge): void
    {
        $this->discountSurcharge = $discountSurcharge;
    }

    public function getLanguage(): ?LanguageEntity
    {
        return $this->language;
    }

    public function setLanguage(LanguageEntity $language): void
    {
        $this->language = $language;
    }
}
