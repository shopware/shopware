<?php declare(strict_types=1);

namespace Shopware\Core\System\Unit;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationCollection;

class UnitStruct extends Entity
{
    /**
     * @var string
     */
    protected $shortCode;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var UnitTranslationCollection|null
     */
    protected $translations;

    /**
     * @var ProductCollection|null
     */
    protected $products;

    public function getShortCode(): string
    {
        return $this->shortCode;
    }

    public function setShortCode(string $shortCode): void
    {
        $this->shortCode = $shortCode;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getTranslations(): ?UnitTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(UnitTranslationCollection $translations): void
    {
        $this->translations = $translations;
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
