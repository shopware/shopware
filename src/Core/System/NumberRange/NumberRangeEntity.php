<?php declare(strict_types=1);

namespace Shopware\Core\System\NumberRange;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateEntity;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

class NumberRangeEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string|null
     */
    protected $typeId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var string|null
     */
    protected $pattern;

    /**
     * @var int|null
     */
    protected $start;

    /**
     * @var \DateTimeInterface|null
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @var NumberRangeTypeEntity|null
     */
    protected $type;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannels;

    /**
     * @var NumberRangeStateEntity|null
     */
    protected $state;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getPattern(): ?string
    {
        return $this->pattern;
    }

    public function setPattern(?string $pattern): void
    {
        $this->pattern = $pattern;
    }

    public function getStart(): ?int
    {
        return $this->start;
    }

    public function setStart(?int $start): void
    {
        $this->start = $start;
    }

    public function getType(): ?NumberRangeTypeEntity
    {
        return $this->type;
    }

    public function setType(?NumberRangeTypeEntity $type): void
    {
        $this->type = $type;
    }

    public function getState(): ?NumberRangeStateEntity
    {
        return $this->state;
    }

    public function setState(?NumberRangeStateEntity $state): void
    {
        $this->state = $state;
    }

    public function getTypeId(): ?string
    {
        return $this->typeId;
    }

    public function setTypeId(?string $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(?SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }
}
