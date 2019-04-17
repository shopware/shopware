<?php
declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Aggregate\PromotionOrderCustomer;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class PromotionOrderCustomerEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    private $promotionId;

    /**
     * @var string
     */
    private $orderId;

    /**
     * @var string
     */
    private $customerId;

    /**
     * @var PromotionEntity|null
     */
    private $promotion;

    /**
     * @var OrderEntity|null
     */
    private $order;

    /**
     * @var CustomerEntity|null
     */
    private $customer;

    public function getPromotionId(): string
    {
        return $this->promotionId;
    }

    public function setPromotionId(string $promotionId): void
    {
        $this->promotionId = $promotionId;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function setCustomerId(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getPromotion(): ?PromotionEntity
    {
        return $this->promotion;
    }

    public function setPromotion(PromotionEntity $promotion): void
    {
        $this->promotion = $promotion;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    public function setCustomer(CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }
}
