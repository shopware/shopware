<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Attribute\Aggregate\AttributeSet;

use Shopware\Core\Framework\Attribute\Aggregate\AttributeSetRelation\AttributeSetRelationCollection;
use Shopware\Core\Framework\Attribute\AttributeCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class AttributeSetEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array|null
     */
    protected $config;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var AttributeCollection|null
     */
    protected $attributes;

    /**
     * @var AttributeSetRelationCollection|null
     */
    protected $relations;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getAttributes(): ?AttributeCollection
    {
        return $this->attributes;
    }

    public function setAttributes(?AttributeCollection $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getRelations(): ?AttributeSetRelationCollection
    {
        return $this->relations;
    }

    public function setRelations(?AttributeSetRelationCollection $relations): void
    {
        $this->relations = $relations;
    }
}
