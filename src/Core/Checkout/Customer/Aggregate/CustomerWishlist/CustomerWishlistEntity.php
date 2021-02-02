<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct\CustomerWishlistProductCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class CustomerWishlistEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $customerId;

    /**
     * @var string
     */
    protected $salesChannelId;

    /**
     * @var CustomerEntity|null
     */
    protected $customer;

    /**
     * @var SalesChannelEntity|null
     */
    protected $salesChannel;

    /**
     * @var CustomerWishlistProductCollection|null
     */
    protected $products;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getProducts(): ?CustomerWishlistProductCollection
    {
        return $this->products;
    }

    public function setProducts(CustomerWishlistProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}
