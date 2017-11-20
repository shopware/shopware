<?php declare(strict_types=1);

namespace Shopware\Config\Struct;

use Shopware\Api\Entity\Entity;

class ConfigFormFieldValueBasicStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $shopUuid;

    /**
     * @var string
     */
    protected $configFormFieldUuid;

    /**
     * @var string
     */
    protected $value;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getShopUuid(): ?string
    {
        return $this->shopUuid;
    }

    public function setShopUuid(?string $shopUuid): void
    {
        $this->shopUuid = $shopUuid;
    }

    public function getConfigFormFieldUuid(): string
    {
        return $this->configFormFieldUuid;
    }

    public function setConfigFormFieldUuid(string $configFormFieldUuid): void
    {
        $this->configFormFieldUuid = $configFormFieldUuid;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
