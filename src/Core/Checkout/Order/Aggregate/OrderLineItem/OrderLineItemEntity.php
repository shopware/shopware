<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderLineItem;

use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceDefinitionInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OrderLineItemEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $orderId;

    /**
     * @var string
     */
    protected $identifier;

    /**
     * @var int
     */
    protected $quantity;

    /**
     * @var float
     */
    protected $unitPrice;

    /**
     * @var float
     */
    protected $totalPrice;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var bool
     */
    protected $good;

    /**
     * @var bool
     */
    protected $removable;

    /**
     * @var string|null
     */
    protected $coverId;

    /**
     * @var bool
     */
    protected $stackable;

    /**
     * @var int
     */
    protected $priority;

    /**
     * @var CalculatedPrice|null
     */
    protected $price;

    /**
     * @var PriceDefinitionInterface|null
     */
    protected $priceDefinition;

    /**
     * @var string[]|null
     */
    protected $payload;

    /**
     * @var string|null
     */
    protected $parentId;

    /**
     * @var string|null
     */
    protected $type;

    /**
     * @var \DateTimeInterface|null
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @var OrderEntity|null
     */
    protected $order;

    /**
     * @var OrderDeliveryPositionCollection|null
     */
    protected $orderDeliveryPositions;

    /**
     * @var OrderLineItemEntity|null
     */
    protected $parent;

    /**
     * @var OrderLineItemCollection|null
     */
    protected $children;

    /**
     * @var array|null
     */
    protected $attributes;

    /**
     * @var MediaEntity|null
     */
    protected $cover;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(float $totalPrice): void
    {
        $this->totalPrice = $totalPrice;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getGood(): bool
    {
        return $this->good;
    }

    public function setGood(bool $good): void
    {
        $this->good = $good;
    }

    public function getRemovable(): bool
    {
        return $this->removable;
    }

    public function setRemovable(bool $removable): void
    {
        $this->removable = $removable;
    }

    public function getStackable(): bool
    {
        return $this->stackable;
    }

    public function setStackable(bool $stackable): void
    {
        $this->stackable = $stackable;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): void
    {
        $this->priority = $priority;
    }

    public function getPrice(): ?CalculatedPrice
    {
        return $this->price;
    }

    public function setPrice(?CalculatedPrice $price): void
    {
        $this->price = $price;
    }

    public function getPriceDefinition(): ?PriceDefinitionInterface
    {
        return $this->priceDefinition;
    }

    public function setPriceDefinition(?PriceDefinitionInterface $priceDefinition): void
    {
        $this->priceDefinition = $priceDefinition;
    }

    public function getPayload(): ?array
    {
        return $this->payload;
    }

    public function setPayload(?array $payload): void
    {
        $this->payload = $payload;
    }

    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    public function setParentId(string $parentId): void
    {
        $this->parentId = $parentId;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getOrderDeliveryPositions(): ?OrderDeliveryPositionCollection
    {
        return $this->orderDeliveryPositions;
    }

    public function setOrderDeliveryPositions(OrderDeliveryPositionCollection $orderDeliveryPositions): void
    {
        $this->orderDeliveryPositions = $orderDeliveryPositions;
    }

    public function getParent(): ?OrderLineItemEntity
    {
        return $this->parent;
    }

    public function setParent(OrderLineItemEntity $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): ?OrderLineItemCollection
    {
        return $this->children;
    }

    public function setChildren(OrderLineItemCollection $children): void
    {
        $this->children = $children;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function getCoverId(): ?string
    {
        return $this->coverId;
    }

    public function setCoverId(?string $coverId): void
    {
        $this->coverId = $coverId;
    }

    public function getCover(): ?MediaEntity
    {
        return $this->cover;
    }

    public function setCover(?MediaEntity $cover): void
    {
        $this->cover = $cover;
    }
}
