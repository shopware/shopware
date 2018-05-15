<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\Framework\ORM\Entity;

class ShopTemplateConfigFormFieldValueBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $shopTemplateConfigFormFieldId;

    /**
     * @var string
     */
    protected $shopId;

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

    public function getShopTemplateConfigFormFieldId(): string
    {
        return $this->shopTemplateConfigFormFieldId;
    }

    public function setShopTemplateConfigFormFieldId(string $shopTemplateConfigFormFieldId): void
    {
        $this->shopTemplateConfigFormFieldId = $shopTemplateConfigFormFieldId;
    }

    public function getShopId(): string
    {
        return $this->shopId;
    }

    public function setShopId(string $shopId): void
    {
        $this->shopId = $shopId;
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
