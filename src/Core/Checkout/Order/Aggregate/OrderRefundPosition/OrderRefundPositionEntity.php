<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderRefundPosition;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderRefund\OrderRefundEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderRefundPositionEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $orderRefundId;

    /**
     * @var string|null
     */
    protected $lineItemId;

    /**
     * @var string[]
     */
    protected $payload;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var CalculatedPrice
     */
    protected $lineItemPrice;

    /**
     * @var float
     */
    protected $lineItemUnitPrice;

    /**
     * @var float
     */
    protected $lineItemTotalPrice;

    /**
     * @var int
     */
    protected $lineItemQuantity;

    /**
     * @var CalculatedPrice
     */
    protected $refundPrice;

    /**
     * @var float
     */
    protected $refundUnitPrice;

    /**
     * @var float
     */
    protected $refundTotalPrice;

    /**
     * @var int
     */
    protected $refundQuantity;

    /**
     * @var OrderLineItemEntity|null
     */
    protected $lineItem;

    /**
     * @var OrderRefundEntity|null
     */
    protected $orderRefund;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getOrderRefundId(): string
    {
        return $this->orderRefundId;
    }

    public function setOrderRefundId(string $orderRefundId): void
    {
        $this->orderRefundId = $orderRefundId;
    }

    public function getLineItemId(): ?string
    {
        return $this->lineItemId;
    }

    public function setLineItemId(?string $lineItemId): void
    {
        $this->lineItemId = $lineItemId;
    }

    public function getRefundUnitPrice(): float
    {
        return $this->refundUnitPrice;
    }

    public function setRefundUnitPrice(float $refundUnitPrice): void
    {
        $this->refundUnitPrice = $refundUnitPrice;
    }

    public function getRefundTotalPrice(): float
    {
        return $this->refundTotalPrice;
    }

    public function setRefundTotalPrice(float $refundTotalPrice): void
    {
        $this->refundTotalPrice = $refundTotalPrice;
    }

    public function getRefundQuantity(): int
    {
        return $this->refundQuantity;
    }

    public function setRefundQuantity(int $refundQuantity): void
    {
        $this->refundQuantity = $refundQuantity;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getLineItemPrice(): CalculatedPrice
    {
        return $this->lineItemPrice;
    }

    public function setLineItemPrice(CalculatedPrice $lineItemPrice): void
    {
        $this->lineItemPrice = $lineItemPrice;
    }

    public function getRefundPrice(): CalculatedPrice
    {
        return $this->refundPrice;
    }

    public function setRefundPrice(CalculatedPrice $refundPrice): void
    {
        $this->refundPrice = $refundPrice;
    }

    public function getLineItem(): ?OrderLineItemEntity
    {
        return $this->lineItem;
    }

    public function setLineItem(OrderLineItemEntity $lineItem): void
    {
        $this->lineItem = $lineItem;
    }

    public function getOrderRefund(): ?OrderRefundEntity
    {
        return $this->orderRefund;
    }

    public function setOrderRefund(OrderRefundEntity $orderRefund): void
    {
        $this->orderRefund = $orderRefund;
    }

    public function getLineItemUnitPrice(): float
    {
        return $this->lineItemUnitPrice;
    }

    public function setLineItemUnitPrice(float $lineItemUnitPrice): void
    {
        $this->lineItemUnitPrice = $lineItemUnitPrice;
    }

    public function getLineItemTotalPrice(): float
    {
        return $this->lineItemTotalPrice;
    }

    public function setLineItemTotalPrice(float $lineItemTotalPrice): void
    {
        $this->lineItemTotalPrice = $lineItemTotalPrice;
    }

    public function getLineItemQuantity(): int
    {
        return $this->lineItemQuantity;
    }

    public function setLineItemQuantity(int $lineItemQuantity): void
    {
        $this->lineItemQuantity = $lineItemQuantity;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }
}
