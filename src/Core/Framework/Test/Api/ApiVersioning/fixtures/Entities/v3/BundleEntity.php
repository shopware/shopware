<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v3;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BundleEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     *
     * @deprecated
     */
    protected $description;

    /**
     * @var bool
     */
    protected $isAbsolute;

    /**
     * @var float
     */
    protected $discount;

    /**
     * @var ProductCollection|null
     */
    protected $products;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @deprecated
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @deprecated
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function isAbsolute(): bool
    {
        return $this->isAbsolute;
    }

    public function setIsAbsolute(bool $isAbsolute): void
    {
        $this->isAbsolute = $isAbsolute;
    }

    public function getDiscount(): float
    {
        return $this->discount;
    }

    public function setDiscount(float $discount): void
    {
        $this->discount = $discount;
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
