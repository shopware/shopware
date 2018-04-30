<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Struct;

use Shopware\Api\Entity\Entity;

class ShopTemplateConfigFormFieldBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $shopTemplateId;

    /**
     * @var string
     */
    protected $shopTemplateConfigFormId;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var string|null
     */
    protected $defaultValue;

    /**
     * @var string|null
     */
    protected $selection;

    /**
     * @var string|null
     */
    protected $fieldLabel;

    /**
     * @var string|null
     */
    protected $supportText;

    /**
     * @var bool
     */
    protected $allowBlank;

    /**
     * @var string|null
     */
    protected $attributes;

    /**
     * @var bool
     */
    protected $lessCompatible;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getShopTemplateId(): string
    {
        return $this->shopTemplateId;
    }

    public function setShopTemplateId(string $shopTemplateId): void
    {
        $this->shopTemplateId = $shopTemplateId;
    }

    public function getShopTemplateConfigFormId(): string
    {
        return $this->shopTemplateConfigFormId;
    }

    public function setShopTemplateConfigFormId(string $shopTemplateConfigFormId): void
    {
        $this->shopTemplateConfigFormId = $shopTemplateConfigFormId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getDefaultValue(): ?string
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(?string $defaultValue): void
    {
        $this->defaultValue = $defaultValue;
    }

    public function getSelection(): ?string
    {
        return $this->selection;
    }

    public function setSelection(?string $selection): void
    {
        $this->selection = $selection;
    }

    public function getFieldLabel(): ?string
    {
        return $this->fieldLabel;
    }

    public function setFieldLabel(?string $fieldLabel): void
    {
        $this->fieldLabel = $fieldLabel;
    }

    public function getSupportText(): ?string
    {
        return $this->supportText;
    }

    public function setSupportText(?string $supportText): void
    {
        $this->supportText = $supportText;
    }

    public function getAllowBlank(): bool
    {
        return $this->allowBlank;
    }

    public function setAllowBlank(bool $allowBlank): void
    {
        $this->allowBlank = $allowBlank;
    }

    public function getAttributes(): ?string
    {
        return $this->attributes;
    }

    public function setAttributes(?string $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getLessCompatible(): bool
    {
        return $this->lessCompatible;
    }

    public function setLessCompatible(bool $lessCompatible): void
    {
        $this->lessCompatible = $lessCompatible;
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
