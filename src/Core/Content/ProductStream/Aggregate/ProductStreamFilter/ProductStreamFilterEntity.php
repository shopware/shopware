<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter;

use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
class ProductStreamFilterEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string|null
     */
    protected $field;

    /**
     * @var string|null
     */
    protected $operator;

    /**
     * @var string|null
     */
    protected $value;

    /**
     * @var string
     */
    protected $productStreamId;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var ProductStreamEntity|null
     */
    protected $productStream;

    /**
     * @var ProductStreamFilterCollection|null
     */
    protected $queries;

    /**
     * @var ProductStreamFilterEntity|null
     */
    protected $parent;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var array|null
     */
    protected $parameters;

    public function getField(): ?string
    {
        return $this->field;
    }

    public function setField(?string $field): void
    {
        $this->field = $field;
    }

    public function getOperator(): ?string
    {
        return $this->operator;
    }

    public function setOperator(?string $operator): void
    {
        $this->operator = $operator;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): void
    {
        $this->value = $value;
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

    public function getProductStream(): ?ProductStreamEntity
    {
        return $this->productStream;
    }

    public function setProductStream(?ProductStreamEntity $productStream): void
    {
        $this->productStream = $productStream;
    }

    public function getQueries(): ?ProductStreamFilterCollection
    {
        return $this->queries;
    }

    public function setQueries(ProductStreamFilterCollection $queries): void
    {
        $this->queries = $queries;
    }

    public function getParent(): ?ProductStreamFilterEntity
    {
        return $this->parent;
    }

    public function setParent(?ProductStreamFilterEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    public function setParameters(?array $parameters): void
    {
        $this->parameters = $parameters;
    }
}
