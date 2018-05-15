<?php declare(strict_types=1);

namespace Shopware\System\Config\Struct;

use Shopware\Framework\ORM\Entity;

class ConfigFormFieldValueBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $configFormFieldId;

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

    public function getConfigFormFieldId(): string
    {
        return $this->configFormFieldId;
    }

    public function setConfigFormFieldId(string $configFormFieldId): void
    {
        $this->configFormFieldId = $configFormFieldId;
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
