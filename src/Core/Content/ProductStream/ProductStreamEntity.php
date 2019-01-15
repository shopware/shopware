<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductStream;

use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamCondition\ProductStreamConditionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\ProductStream\ProductStream;

class ProductStreamEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var int
     */
    protected $priority;

    /**
     * @var ProductStream|null
     */
    protected $payload;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var ProductStreamConditionCollection|null
     */
    protected $conditions;

    /**
     * @var bool
     */
    protected $invalid;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getPayload(): ?ProductStream
    {
        return $this->payload;
    }

    public function setPayload(?ProductStream $payload): void
    {
        $this->payload = $payload;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getConditions(): ?ProductStreamConditionCollection
    {
        return $this->conditions;
    }

    public function setConditions(ProductStreamConditionCollection $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function isInvalid(): bool
    {
        return $this->invalid;
    }

    public function setInvalid(bool $invalid): void
    {
        $this->invalid = $invalid;
    }
}
