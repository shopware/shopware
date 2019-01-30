<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class AttributeSetRelationEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $entityName;

    /**
     * @var string
     */
    protected $attributeSetId;

    /**
     * @var AttributeSetEntity
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

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): void
    {
        $this->entityName = $entityName;
    }

    public function getAttributeSetId(): string
    {
        return $this->attributeSetId;
    }

    public function setAttributeSetId(string $attributeSetId): void
    {
        $this->attributeSetId = $attributeSetId;
    }

    public function getAttributeSet(): AttributeSetEntity
    {
        return $this->attributeSet;
    }

    public function setAttributeSet(AttributeSetEntity $attributeSet): void
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
