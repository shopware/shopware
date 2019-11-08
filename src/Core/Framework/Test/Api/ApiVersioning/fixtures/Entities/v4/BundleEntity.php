<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiVersioning\fixtures\Entities\v4;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BundleEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

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

    /**
     * @var EntityCollection|null
     */
    protected $prices;

    /**
     * @var float
     *
     * @deprecated use prices.pseudoPrice instead
     */
    private $pseudoPrice;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    /**
     * @deprecated use prices->getPseudoPrice() instead
     */
    public function getPseudoPrice(): float
    {
        return $this->pseudoPrice;
    }

    /**
     * @deprecated use prices->setPseudoPrice() instead
     */
    public function setPseudoPrice(float $pseudoPrice): void
    {
        $this->pseudoPrice = $pseudoPrice;
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getPrices(): ?EntityCollection
    {
        return $this->prices;
    }

    public function setPrices(EntityCollection $prices): void
    {
        $this->prices = $prices;
    }
}
