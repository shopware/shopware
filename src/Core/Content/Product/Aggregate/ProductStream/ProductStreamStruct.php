<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductStream;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Listing\ListingSortingStruct;

class ProductStreamStruct extends Entity
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
     * @var ListingSortingStruct|null
     */
    protected $listingSorting;

    /**
     * @var ProductCollection|null
     */
    protected $products;

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

    public function getListingSorting(): ?ListingSortingStruct
    {
        return $this->listingSorting;
    }

    public function setListingSorting(?ListingSortingStruct $listingSorting): void
    {
        $this->listingSorting = $listingSorting;
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }
}
