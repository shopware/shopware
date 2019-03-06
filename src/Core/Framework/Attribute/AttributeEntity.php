<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\Attribute\Aggregate\AttributeSet\AttributeSetEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class AttributeEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;
    /**
     * @var string
     */
    protected $type;

    /**
     * @var array|null
     */
    protected $config;

    /**
     * @var string|null
     */
    protected $attributeSetId;

    /**
     * @var AttributeSetEntity|null
     */
    protected $attributeSet;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }

    public function getAttributeSetId(): ?string
    {
        return $this->attributeSetId;
    }

    public function setAttributeSetId(?string $attributeSetId): void
    {
        $this->attributeSetId = $attributeSetId;
    }

    public function getAttributeSet(): ?AttributeSetEntity
    {
        return $this->attributeSet;
    }

    public function setAttributeSet(?AttributeSetEntity $attributeSet): void
    {
        $this->attributeSet = $attributeSet;
    }

    public function getCreatedAt(): \DateTime
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
