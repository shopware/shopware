<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation;

use Shopware\Core\Checkout\Shipping\ShippingMethodStruct;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\System\Language\LanguageStruct;

class ShippingMethodTranslationStruct extends Entity
{
    /**
     * @var string
     */
    protected $shippingMethodId;

    /**
     * @var string
     */
    protected $languageId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $comment;

    /**
     * @var ShippingMethodStruct|null
     */
    protected $shippingMethod;

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

    public function getShippingMethodId(): string
    {
        return $this->shippingMethodId;
    }

    public function setShippingMethodId(string $shippingMethodId): void
    {
        $this->shippingMethodId = $shippingMethodId;
    }

    public function getLanguageId(): string
    {
        return $this->languageId;
    }

    public function setLanguageId(string $languageId): void
    {
        $this->languageId = $languageId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): void
    {
        $this->comment = $comment;
    }

    public function getShippingMethod(): ?ShippingMethodStruct
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(ShippingMethodStruct $shippingMethod): void
    {
        $this->shippingMethod = $shippingMethod;
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
