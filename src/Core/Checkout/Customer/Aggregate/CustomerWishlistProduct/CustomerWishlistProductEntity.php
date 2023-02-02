<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CustomerWishlistProductEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $wishlistId;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var CustomerWishlistEntity|null
     */
    protected $wishlist;

    /**
     * @var ProductEntity|null
     */
    protected $product;

    public function getWishlist(): ?CustomerWishlistEntity
    {
        return $this->wishlist;
    }

    public function setWishlist(CustomerWishlistEntity $wishlist): void
    {
        $this->wishlist = $wishlist;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function setWishlistId(string $wishlistId): void
    {
        $this->wishlistId = $wishlistId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }
}
