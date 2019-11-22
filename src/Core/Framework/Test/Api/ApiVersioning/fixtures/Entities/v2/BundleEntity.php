<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v2;

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
     */
    protected $description;

    /**
     * @var string
     *
     * @deprecated
     */
    protected $longDescription;

    /**
     * @var string
     *
     * @deprecated use `$isAbsolute` instead
     */
    protected $discountType;

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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @deprecated
     */
    public function getLongDescription(): string
    {
        return $this->longDescription;
    }

    /**
     * @deprecated
     */
    public function setLongDescription(string $longDescription): void
    {
        $this->longDescription = $longDescription;
    }

    /**
     * @deprecated use `isAbsolute()` instead
     */
    public function getDiscountType(): string
    {
        return $this->discountType;
    }

    /**
     * @deprecated use `setIsAbsolute()` instead
     */
    public function setDiscountType(string $discountType): void
    {
        $this->discountType = $discountType;
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
