<?php declare(strict_types=1);

namespace Shopware\Api\Product\Struct;

use Shopware\Api\Entity\Entity;
use Shopware\System\Listing\Struct\ListingSortingBasicStruct;

class ProductStreamBasicStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $listingSortingId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $conditions;

    /**
     * @var int|null
     */
    protected $type;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var ListingSortingBasicStruct|null
     */
    protected $listingSorting;

    public function getListingSortingId(): ?string
    {
        return $this->listingSortingId;
    }

    public function setListingSortingId(?string $listingSortingId): void
    {
        $this->listingSortingId = $listingSortingId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): void
    {
        $this->conditions = $conditions;
    }

    public function getType(): ?int
    {
        return $this->type;
    }

    public function setType(?int $type): void
    {
        $this->type = $type;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
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

    public function getListingSorting(): ?ListingSortingBasicStruct
    {
        return $this->listingSorting;
    }

    public function setListingSorting(?ListingSortingBasicStruct $listingSorting): void
    {
        $this->listingSorting = $listingSorting;
    }
}
