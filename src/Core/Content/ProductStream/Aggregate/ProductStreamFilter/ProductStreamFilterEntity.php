<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream\Aggregate\ProductStreamFilter;

use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductStreamFilterEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $type;

    protected ?string $field = null;

    protected ?string $operator = null;

    protected ?string $value = null;

    protected string $productStreamId;

    protected ?string $parentId = null;

    protected ?ProductStreamEntity $productStream = null;

    protected ?ProductStreamFilterCollection $queries = null;

    protected ?ProductStreamFilterEntity $parent = null;

    protected int $position;

    /**
     * @var array<string>|null
     */
    protected ?array $parameters = null;

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

    /**
     * @return array<string>|null
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }

    /**
     * @param array<string>|null $parameters
     */
    public function setParameters(?array $parameters): void
    {
        $this->parameters = $parameters;
    }
}
