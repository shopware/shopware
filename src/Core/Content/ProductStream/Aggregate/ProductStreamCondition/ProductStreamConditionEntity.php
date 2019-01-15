<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamCondition;

use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductStreamConditionEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $type;

    /**
     * @var string
     */
    protected $productStreamId;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var array|null
     */
    protected $value;

    /**
     * @var ProductStreamEntity|null
     */
    protected $productStream;

    /**
     * @var ProductStreamConditionCollection|null
     */
    protected $children;

    /**
     * @var ProductStreamConditionEntity|null
     */
    protected $parent;

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getProductStreamId(): string
    {
        return $this->productStreamId;
    }

    public function setProductStreamId(string $productStreamId): void
    {
        $this->productStreamId = $productStreamId;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(?string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getValue(): ?array
    {
        return $this->value;
    }

    public function setValue(?array $value): void
    {
        $this->value = $value;
    }

    public function getProductStream(): ?ProductStreamEntity
    {
        return $this->productStream;
    }

    public function setProductStream(?ProductStreamEntity $productStream): void
    {
        $this->productStream = $productStream;
    }

    public function getChildren(): ?ProductStreamConditionCollection
    {
        return $this->children;
    }

    public function setChildren(?ProductStreamConditionCollection $children): void
    {
        $this->children = $children;
    }

    public function getParent(): ?ProductStreamConditionEntity
    {
        return $this->parent;
    }

    public function setParent(?ProductStreamConditionEntity $parent): void
    {
        $this->parent = $parent;
    }
}
