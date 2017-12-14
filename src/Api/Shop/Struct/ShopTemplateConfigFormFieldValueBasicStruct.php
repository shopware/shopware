<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\Api\Entity\Entity;

class ShopTemplateConfigFormFieldValueBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $shopTemplateConfigFormFieldUuid;

    /**
     * @var string
     */
    protected $shopUuid;

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

    public function getShopTemplateConfigFormFieldUuid(): string
    {
        return $this->shopTemplateConfigFormFieldUuid;
    }

    public function setShopTemplateConfigFormFieldUuid(string $shopTemplateConfigFormFieldUuid): void
    {
        $this->shopTemplateConfigFormFieldUuid = $shopTemplateConfigFormFieldUuid;
    }

    public function getShopUuid(): string
    {
        return $this->shopUuid;
    }

    public function setShopUuid(string $shopUuid): void
    {
        $this->shopUuid = $shopUuid;
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
